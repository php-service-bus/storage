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
use ServiceBus\Storage\Common\DatabaseAdapter;
use function Amp\call;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\find;

/**
 *
 */
final class SimpleSqlFinder implements SqlFinder
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
     * @psalm-param non-empty-string $collectionName
     */
    public function __construct(string $collectionName, DatabaseAdapter $databaseAdapter)
    {
        $this->collectionName  = $collectionName;
        $this->databaseAdapter = $databaseAdapter;
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
                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield find(
                    queryExecutor: $this->databaseAdapter,
                    tableName: $this->collectionName,
                    criteria: $criteria
                );

                /** @var array|null $result */
                $result = yield fetchOne($resultSet);

                if (\is_array($result) && \count($result) !== 0)
                {
                    return $result;
                }

                return null;
            }
        );
    }

    public function find(array $criteria, ?int $offset = null, ?int $limit = null, ?array $orderBy = null): Promise
    {
        return call(
            function () use ($criteria, $offset, $limit, $orderBy): \Generator
            {
                /** @var \ServiceBus\Storage\Common\ResultSet $resultSet */
                $resultSet = yield find(
                    queryExecutor: $this->databaseAdapter,
                    tableName: $this->collectionName,
                    criteria: $criteria,
                    limit: $limit,
                    offset: $offset,
                    orderBy: $orderBy
                );

                /** @var array $collection */
                $collection = yield fetchAll($resultSet);

                return $collection;
            }
        );
    }
}
