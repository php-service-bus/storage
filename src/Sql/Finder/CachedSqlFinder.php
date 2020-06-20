<?php

/**
 * SQL adapters support module.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

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
     * @var string
     */
    private $collectionName;

    /** @var DatabaseAdapter */
    private $databaseAdapter;

    /** @var CacheAdapter */
    private $cacheAdapter;

    public function __construct(string $collectionName, DatabaseAdapter $databaseAdapter, ?CacheAdapter $cacheAdapter = null)
    {
        $this->collectionName  = $collectionName;
        $this->databaseAdapter = $databaseAdapter;
        $this->cacheAdapter    = $cacheAdapter ?? new InMemoryCacheAdapter();
    }

    /**
     * @inheritDoc
     */
    public function findOneById($id): Promise
    {
        return $this->findOneBy([equalsCriteria('id', $id)]);
    }

    /**
     * @inheritDoc
     */
    public function findOneBy(array $criteria): Promise
    {
        return call(
            function () use ($criteria): \Generator
            {
                /**
                 * @psalm-var string $sql
                 * @psalm-var array<string, string|int|float|null> $parameters
                 * @psalm-var string $cacheKey
                 */
                [$sql, $parameters, $cacheKey] = self::doPrepare($this->collectionName, $criteria, null, []);

                /** @var bool $hasEntry */
                $hasEntry = yield $this->cacheAdapter->has($cacheKey);

                if ($hasEntry === false)
                {
                    /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                    $resultSet = yield $this->databaseAdapter->execute($sql, $parameters);

                    /** @var array|null $data */
                    $data = yield fetchOne($resultSet);

                    unset($resultSet);

                    if ($data !== null)
                    {
                        yield $this->cacheAdapter->save($cacheKey, $data);
                    }
                }

                /** @var array $data */
                $data = yield $this->cacheAdapter->get($cacheKey);

                return $data;
            }
        );
    }

    /**
     * @inheritDoc
     */
    public function find(array $criteria, ?int $limit = null, array $orderBy = []): Promise
    {
        return call(
            function () use ($criteria, $limit, $orderBy): \Generator
            {
                /**
                 * @psalm-var string $sql
                 * @psalm-var array<string, string|int|float|null> $parameters
                 * @psalm-var string $cacheKey
                 */
                [$sql, $parameters, $cacheKey] = self::doPrepare($this->collectionName, $criteria, $limit, $orderBy);

                /** @var bool $hasEntry */
                $hasEntry = yield $this->cacheAdapter->has($cacheKey);

                if ($hasEntry === false)
                {
                    /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                    $resultSet = yield $this->databaseAdapter->execute($sql, $parameters);

                    /** @var array|null $data */
                    $data = yield fetchAll($resultSet);

                    if (\is_array($data) === false || \count($data) === 0)
                    {
                        return [];
                    }

                    yield $this->cacheAdapter->save($cacheKey, $data);
                }

                /** @var array $data */
                $data = yield $this->cacheAdapter->get($cacheKey);

                return $data;
            }
        );
    }

    /**
     * @psalm-param array<mixed, \Latitude\QueryBuilder\CriteriaInterface> $criteria
     * @psalm-param array<string, string>                                  $orderBy
     *
     * @param \Latitude\QueryBuilder\CriteriaInterface[] $criteria
     *
     * @return array array 0 - SQL query; 1 - query parameters; 2 - cache key
     */
    private static function doPrepare(string $collectionName, array $criteria, ?int $limit, array $orderBy): array
    {
        $query = buildQuery(
            selectQuery($collectionName),
            $criteria,
            $orderBy,
            $limit
        );

        $query[] = \sha1(\serialize($query));

        return $query;
    }
}
