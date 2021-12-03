<?php

/**
 * SQL database adapter implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\Sql;

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
use function Amp\call;
use function Latitude\QueryBuilder\field;

/**
 * Collect iterator data
 * Not recommended for use on large amounts of data.
 *
 * @psalm-return Promise<array<array-key, array<string, float|int|resource|string|null>>>
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
                $result = $iterator->getCurrent();

                if ($result !== null)
                {
                    $array[] = $result;
                }
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
 * @psalm-return Promise<array<string, mixed>|null>
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
 * @psalm-param non-empty-string $sequenceName
 *
 * @psalm-return Promise<string>
 */
function sequence(string $sequenceName, QueryExecutor $executor): Promise
{
    return call(
        static function () use ($sequenceName, $executor): \Generator
        {
            /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
            $resultSet = yield $executor->execute(\sprintf('SELECT nextval(\'%s\')', $sequenceName));

            /**
             * @psalm-var array{nextval: non-empty-string} $result
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
 * @psalm-param non-empty-string                                       $tableName
 * @psalm-param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 * @psalm-param positive-int|null                                      $limit
 * @psalm-param array<non-empty-string, non-empty-string>|null         $orderBy
 *
 * @psalm-return Promise<\ServiceBus\Storage\Common\ResultSet>
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed Could not connect to database
 * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
 * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed Basic type of interaction errors
 * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
 */
function find(
    QueryExecutor $queryExecutor,
    string        $tableName,
    array         $criteria = [],
    ?int          $limit = null,
    ?int          $offset = null,
    ?array        $orderBy = null
): Promise {
    return call(
        static function () use ($queryExecutor, $tableName, $criteria, $offset, $limit, $orderBy): \Generator
        {
            $queryData = buildQuery(
                queryBuilder: selectQuery($tableName),
                criteria: $criteria,
                orderBy: $orderBy,
                offset: $offset,
                limit: $limit
            );

            return yield $queryExecutor->execute($queryData['query'], $queryData['parameters']);
        }
    );
}

/**
 * Create & execute DELETE query.
 *
 * @psalm-param non-empty-string                                           $tableName
 * @psalm-param array<array-key, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 *
 * @psalm-return Promise<int>
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
            $queryData = buildQuery(
                queryBuilder: deleteQuery($tableName),
                criteria: $criteria
            );

            /**
             * @var \ServiceBus\Storage\Common\ResultSet $resultSet
             */
            $resultSet = yield $queryExecutor->execute($queryData['query'], $queryData['parameters']);

            $affectedRows = $resultSet->affectedRows();

            unset($resultSet);

            return $affectedRows;
        }
    );
}

/**
 * Create query from specified parameters.
 *
 * @psalm-param array<array-key, \Latitude\QueryBuilder\CriteriaInterface> $criteria
 * @psalm-param array<non-empty-string, non-empty-string>|null             $orderBy
 * @psalm-param positive-int|null                                          $limit
 *
 * @psalm-return array{query:non-empty-string, parameters: array<array-key, string|int|float|null>}
 */
function buildQuery(
    LatitudeQuery\AbstractQuery $queryBuilder,
    array                       $criteria = [],
    ?array                      $orderBy = null,
    ?int                        $offset = null,
    ?int                        $limit = null
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
        if ($orderBy !== null)
        {
            foreach ($orderBy as $column => $direction)
            {
                $queryBuilder->orderBy($column, $direction);
            }
        }

        if ($limit !== null)
        {
            $queryBuilder->limit($limit);
        }

        if ($offset !== null)
        {
            $queryBuilder->offset($offset);
        }
    }

    $compiledQuery = $queryBuilder->compile();

    /** @psalm-var non-empty-string $query */
    $query = $compiledQuery->sql();

    /** @psalm-var array<array-key, string|int|float|null> $parameters */
    $parameters = $compiledQuery->params();

    return [
        'query'      => $query,
        'parameters' => $parameters
    ];
}

/**
 * Unescape binary data.
 *
 * @psalm-param array<string, string|int|null|float>|string $data
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
 * @psalm-param non-empty-string $field
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 */
function equalsCriteria(string $field, float|int|object|string $value): CriteriaInterface
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
 * @psalm-param non-empty-string $field
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 */
function notEqualsCriteria(string $field, float|int|object|string $value): CriteriaInterface
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
 * @psalm-param non-empty-string $fromTable
 */
function selectQuery(string $fromTable, string ...$columns): LatitudeQuery\SelectQuery
{
    return queryBuilder()->select(...$columns)->from($fromTable);
}

/**
 * Create update query (for PostgreSQL).
 *
 * @psalm-param non-empty-string                      $tableName
 * @psalm-param array<non-empty-string, mixed>|object $toUpdate
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 */
function updateQuery(string $tableName, array|object $toUpdate): LatitudeQuery\UpdateQuery
{
    $values = \is_object($toUpdate) ? castObjectToArray($toUpdate) : $toUpdate;

    return queryBuilder()->update($tableName, $values);
}

/**
 * Create delete query (for PostgreSQL).
 *
 * @psalm-param non-empty-string $fromTable
 */
function deleteQuery(string $fromTable): LatitudeQuery\DeleteQuery
{
    return queryBuilder()->delete($fromTable);
}

/**
 * Create insert query (for PostgreSQL).
 *
 * @psalm-param non-empty-string                      $toTable
 * @psalm-param array<non-empty-string, mixed>|object $toInsert
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
 */
function insertQuery(string $toTable, array|object $toInsert): LatitudeQuery\InsertQuery
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
 * @psalm-return array<non-empty-string, float|int|object|string|null>
 */
function getObjectVars(object $object): array
{
    /** @psalm-var \Closure():array<non-empty-string, float|int|object|string|null> $closure */
    $closure = \Closure::bind(
        function (): array
        {
            /** @psalm-var object $this */
            return \get_object_vars($this);
        },
        $object,
        $object
    );

    return $closure();
}

/**
 * @internal
 *
 * @psalm-param non-empty-string $string
 *
 * @psalm-return non-empty-string
 *
 * Convert string from lowerCamelCase to snake_case
 */
function toSnakeCase(string $string): string
{
    $replaced = \preg_replace('/(?<!^)[A-Z]/', '_$0', $string);

    if (\is_string($replaced))
    {
        $string = \strtolower($replaced);
    }

    /** @psalm-var non-empty-string $string */

    return $string;
}

/**
 * @internal
 *
 * @throws \ServiceBus\Storage\Common\Exceptions\IncorrectParameterCast
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
