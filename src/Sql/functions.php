<?php

/**
 * SQL database adapter implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Storage\Sql;

use function Amp\call;
use function Latitude\QueryBuilder\field;
use Amp\Promise;
use Latitude\QueryBuilder\CriteriaInterface;
use Latitude\QueryBuilder\Engine\PostgresEngine;
use Latitude\QueryBuilder\EngineInterface;
use Latitude\QueryBuilder\Query as LatitudeQuery;
use Latitude\QueryBuilder\QueryFactory;
use ServiceBus\Storage\Common\BinaryDataDecoder;
use ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast;
use ServiceBus\Storage\Common\Exceptions\OneResultExpected;
use ServiceBus\Storage\Common\QueryExecutor;
use ServiceBus\Storage\Common\ResultSet;

/**
 * Collect iterator data
 * Not recommended for use on large amounts of data.
 *
 * @return Promise<array<int, mixed>>
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
 */
function fetchAll(ResultSet $iterator): Promise
{
    return call(
        static function () use ($iterator): \Generator
        {
            $array = [];

            while (yield $iterator->advance())
            {
                $array[] = $iterator->getCurrent();
            }

            return $array;
        }
    );
}

/**
 * Extract 1 result.
 *
 * @psalm-suppress MixedReturnTypeCoercion
 *
 * @return Promise<array<string, mixed>|null>
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
 * @throws \ServiceBus\Storage\Common\Exceptions\OneResultExpected The result must contain only 1 row
 */
function fetchOne(ResultSet $iterator): Promise
{
    return call(
        static function () use ($iterator): \Generator
        {
            /** @var array $collection */
            $collection   = yield fetchAll($iterator);
            $resultsCount = \count($collection);

            if ($resultsCount === 0 || $resultsCount === 1)
            {
                /** @var array|bool $endElement */
                $endElement = \end($collection);

                if ($endElement !== false)
                {
                    return $endElement;
                }

                return null;
            }

            throw new OneResultExpected(
                \sprintf(
                    'A single record was requested, but the result of the query execution contains several ("%d")',
                    $resultsCount
                )
            );
        }
    );
}

/**
 * Returns the value of the specified sequence (string).
 *
 * @return Promise<string>
 */
function sequence(string $sequenceName, QueryExecutor $executor): Promise
{
    return call(
        static function () use ($sequenceName, $executor): \Generator
        {
            /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
            $resultSet = yield $executor->execute(\sprintf('SELECT nextval(\'%s\')', $sequenceName));

            /**
             * @psalm-var array{nextval: string} $result
             */
            $result = yield fetchOne($resultSet);

            unset($resultSet);

            return $result['nextval'];
        }
    );
}

/**
 * Create & execute SELECT query.
 *
 * @psalm-param    array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 * @psalm-param    array<string, string>                                  $orderBy
 *
 * @param \Latitude\QueryBuilder\CriteriaInterface[]                      $criteria
 *
 * @return Promise<\ServiceBus\Storage\Common\ResultSet>
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
 * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
 * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
 * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
 */
function find(QueryExecutor $queryExecutor, string $tableName, array $criteria = [], ?int $limit = null, array $orderBy = []): Promise
{
    return call(
        static function () use ($queryExecutor, $tableName, $criteria, $limit, $orderBy): \Generator
        {
            /**
             * @var string $query
             * @var array  $parameters
             */
            [$query, $parameters] = buildQuery(selectQuery($tableName), $criteria, $orderBy, $limit);

            /** @psalm-var array<string, string|int|float|null> $parameters */

            return yield $queryExecutor->execute($query, $parameters);
        }
    );
}

/**
 * Create & execute DELETE query.
 *
 * @psalm-param  array<array-key, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 *
 * @param \Latitude\QueryBuilder\CriteriaInterface[]                        $criteria
 *
 * @return Promise<int>
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
 * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
 * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
 * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
 * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
 */
function remove(QueryExecutor $queryExecutor, string $tableName, array $criteria = []): Promise
{
    return call(
        static function () use ($queryExecutor, $tableName, $criteria): \Generator
        {
            /**
             * @var string                                        $query
             * @psalm-var array<array-key, string|int|float|null> $parameters
             */
            [$query, $parameters] = buildQuery(deleteQuery($tableName), $criteria);

            /**
             * @var \ServiceBus\Storage\Common\ResultSet $resultSet
             */
            $resultSet = yield $queryExecutor->execute($query, $parameters);

            $affectedRows = $resultSet->affectedRows();

            unset($resultSet);

            return $affectedRows;
        }
    );
}

/**
 * Create query from specified parameters.
 *
 * @psalm-param array<string, string>                $orderBy
 *
 * @param \Latitude\QueryBuilder\CriteriaInterface[] $criteria
 *
 * @return array 0 - SQL query; 1 - query parameters
 */
function buildQuery(
    LatitudeQuery\AbstractQuery $queryBuilder,
    array $criteria = [],
    array $orderBy = [],
    ?int $limit = null
): array {
    /** @var LatitudeQuery\DeleteQuery|LatitudeQuery\SelectQuery|LatitudeQuery\UpdateQuery $queryBuilder */
    $isFirstCondition = true;

    foreach ($criteria as $criteriaItem)
    {
        $methodName = $isFirstCondition ? 'where' : 'andWhere';
        $queryBuilder->{$methodName}($criteriaItem);
        $isFirstCondition = false;
    }

    if ($queryBuilder instanceof LatitudeQuery\SelectQuery)
    {
        foreach ($orderBy as $column => $direction)
        {
            $queryBuilder->orderBy($column, $direction);
        }

        if (null !== $limit)
        {
            $queryBuilder->limit($limit);
        }
    }

    $compiledQuery = $queryBuilder->compile();

    return [
        $compiledQuery->sql(),
        $compiledQuery->params(),
    ];
}

/**
 * Unescape binary data.
 *
 * @psalm-param  array<string, string|int|null|float>|string $data
 *
 * @psalm-return array<string, string|int|null|float>|string
 */
function unescapeBinary(QueryExecutor $queryExecutor, array|string $data): array|string
{
    if ($queryExecutor instanceof BinaryDataDecoder)
    {
        if (\is_array($data) === false)
        {
            return $queryExecutor->unescapeBinary($data);
        }

        foreach ($data as $key => $value)
        {
            if (empty($value) === false && \is_string($value))
            {
                $data[$key] = $queryExecutor->unescapeBinary($value);
            }
        }
    }

    return $data;
}

/**
 * Create equals criteria.
 *
 * @param float|int|object|string $value
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 */
function equalsCriteria(string $field, $value): CriteriaInterface
{
    if (\is_object($value))
    {
        $value = castObjectToString($value);
    }

    return field($field)->eq($value);
}

/**
 * Create not equals criteria.
 *
 * @param float|int|object|string $value
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 */
function notEqualsCriteria(string $field, $value): CriteriaInterface
{
    if (\is_object($value))
    {
        $value = castObjectToString($value);
    }

    return field($field)->notEq($value);
}

/**
 * Create query builder.
 */
function queryBuilder(EngineInterface $engine = null): QueryFactory
{
    return new QueryFactory($engine ?? new PostgresEngine());
}

/**
 * Create select query (for PostgreSQL).
 *
 * @noinspection PhpDocSignatureInspection
 */
function selectQuery(string $fromTable, string ...$columns): LatitudeQuery\SelectQuery
{
    return queryBuilder()->select(...$columns)->from($fromTable);
}

/**
 * Create update query (for PostgreSQL).
 *
 * @psalm-param array<string, mixed>|object $toUpdate
 *
 * @param array|object                      $toUpdate
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 */
function updateQuery(string $tableName, $toUpdate): LatitudeQuery\UpdateQuery
{
    $values = \is_object($toUpdate) ? castObjectToArray($toUpdate) : $toUpdate;

    return queryBuilder()->update($tableName, $values);
}

/**
 * Create delete query (for PostgreSQL).
 */
function deleteQuery(string $fromTable): LatitudeQuery\DeleteQuery
{
    return queryBuilder()->delete($fromTable);
}

/**
 * Create insert query (for PostgreSQL).
 *
 * @psalm-param array<string, mixed>|object $toInsert
 *
 * @param array|object                      $toInsert
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 */
function insertQuery(string $toTable, $toInsert): LatitudeQuery\InsertQuery
{
    $rows = \is_object($toInsert) ? castObjectToArray($toInsert) : $toInsert;

    return queryBuilder()->insert($toTable, $rows);
}

/**
 * Receive object as array (property/value).
 *
 * @internal
 *
 * @psalm-return array<string, float|int|string|null>
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 */
function castObjectToArray(object $object): array
{
    $result = [];

    /** @var float|int|object|string|null $value */
    foreach (getObjectVars($object) as $key => $value)
    {
        $result[toSnakeCase($key)] = cast($value);
    }

    return $result;
}

/**
 * Gets the properties of the given object.
 *
 * @internal
 *
 * @psalm-return array<string, float|int|object|string|null>
 */
function getObjectVars(object $object): array
{
    /** @var \Closure $closure */
    $closure = \Closure::bind(
        function (): array
        {
            /** @psalm-var object $this */
            return \get_object_vars($this);
        },
        $object,
        $object
    );

    /**
     * @psalm-var array<string, float|int|object|string|null> $vars
     *
     * @var array                                             $vars
     */
    $vars = $closure();

    return $vars;
}

/**
 * @internal
 *
 * Convert string from lowerCamelCase to snake_case
 */
function toSnakeCase(string $string): string
{
    $replaced = \preg_replace('/(?<!^)[A-Z]/', '_$0', $string);

    if (\is_string($replaced))
    {
        return \strtolower($replaced);
    }

    return $string;
}

/**
 * @internal
 *
 * @param float|int|object|string|null $value
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 *
 * @return float|int|string|null
 */
function cast(float|int|object|string|null $value): float|int|string|null
{
    if ($value === null || \is_scalar($value))
    {
        return $value;
    }

    return castObjectToString($value);
}

/**
 * Cast object to string.
 *
 * @internal
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 */
function castObjectToString(object $object): string
{
    if (\method_exists($object, '__toString'))
    {
        return (string) $object;
    }

    throw new IncorrectParameterCast(
        \sprintf('"%s" must implements "__toString" method', \get_class($object))
    );
}
