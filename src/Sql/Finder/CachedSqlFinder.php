<?php

/**
 * SQL adapters support module.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\Sql\Finder;

use Amp\Promise;
use ServiceBus\Cache\CacheAdapter;
use ServiceBus\Cache\InMemory\InMemoryCacheAdapter;
use ServiceBus\Storage\Common\DatabaseAdapter;
use function Amp\call;
use function ServiceBus\Storage\Sql\buildQuery;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\selectQuery;

/**
 * Search a collection with caching the result.
 */
final class CachedSqlFinder implements SqlFinder
{
    /**
     * Table name.
     *
     * @psalm-var non-empty-string
     *
     * @var string
     */
    private $collectionName;

    /**
     * @var DatabaseAdapter
     */
    private $databaseAdapter;

    /**
     * @var CacheAdapter
     */
    private $cacheAdapter;

    /**
     * @psalm-param non-empty-string $collectionName
     */
    public function __construct(
        string          $collectionName,
        DatabaseAdapter $databaseAdapter,
        ?CacheAdapter   $cacheAdapter = null
    ) {
        $this->collectionName  = $collectionName;
        $this->databaseAdapter = $databaseAdapter;
        $this->cacheAdapter    = $cacheAdapter ?? new InMemoryCacheAdapter();
    }

    public function findOneById(string|int $id): Promise
    {
        return $this->findOneBy([equalsCriteria('id', $id)]);
    }

    public function findOneBy(array $criteria): Promise
    {
        return call(
            function () use ($criteria): \Generator
            {
                $queryData = self::doPrepare(
                    collectionName: $this->collectionName,
                    criteria: $criteria
                );

                /** @var bool $hasEntry */
                $hasEntry = yield $this->cacheAdapter->has($queryData['cacheKey']);

                if ($hasEntry === false)
                {
                    /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                    $resultSet = yield $this->databaseAdapter->execute($queryData['query'], $queryData['parameters']);

                    /** @var array|null $data */
                    $data = yield fetchOne($resultSet);

                    unset($resultSet);

                    if ($data !== null)
                    {
                        yield $this->cacheAdapter->save($queryData['cacheKey'], $data);
                    }
                }

                /** @var array $data */
                $data = yield $this->cacheAdapter->get($queryData['cacheKey']);

                return $data;
            }
        );
    }

    public function find(array $criteria, ?int $offset = null, ?int $limit = null, ?array $orderBy = null): Promise
    {
        return call(
            function () use ($criteria, $offset, $limit, $orderBy): \Generator
            {
                $queryData = self::doPrepare(
                    collectionName: $this->collectionName,
                    criteria: $criteria,
                    offset: $offset,
                    limit: $limit,
                    orderBy: $orderBy
                );

                /** @var bool $hasEntry */
                $hasEntry = yield $this->cacheAdapter->has($queryData['cacheKey']);

                if ($hasEntry === false)
                {
                    /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                    $resultSet = yield $this->databaseAdapter->execute($queryData['query'], $queryData['parameters']);

                    /** @var array|null $data */
                    $data = yield fetchAll($resultSet);

                    if (\is_array($data) === false || \count($data) === 0)
                    {
                        return [];
                    }

                    yield $this->cacheAdapter->save($queryData['cacheKey'], $data);
                }

                /** @var array $data */
                $data = yield $this->cacheAdapter->get($queryData['cacheKey']);

                return $data;
            }
        );
    }

    /**
     * @psalm-param non-empty-string                                       $collectionName
     * @psalm-param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
     * @psalm-param array<non-empty-string, non-empty-string>|null         $orderBy
     * @psalm-param positive-int|null                                      $limit
     *
     * @psalm-return array{
     *     cacheKey:non-empty-string,
     *     query:non-empty-string,
     *     parameters: array<array-key, string|int|float|null>
     * }
     */
    private static function doPrepare(
        string $collectionName,
        array  $criteria,
        ?int   $offset = null,
        ?int   $limit = null,
        ?array $orderBy = null
    ): array {
        $queryData = buildQuery(
            queryBuilder: selectQuery($collectionName),
            criteria: $criteria,
            orderBy: $orderBy,
            offset: $offset,
            limit: $limit
        );

        /** @psalm-var non-empty-string $cacheKey */
        $cacheKey = \sha1(\serialize($queryData));

        return [
            'cacheKey'   => $cacheKey,
            'query'      => $queryData['query'],
            'parameters' => $queryData['parameters']
        ];
    }
}
