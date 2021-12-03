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

/**
 *
 */
interface SqlFinder
{
    /**
     * Returns an array if successful and null if no record
     *
     * @psalm-return Promise<array|null>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\OneResultExpected
     */
    public function findOneById(string|int $id): Promise;

    /**
     * Returns an array if successful and null if no record
     *
     * @psalm-param array<array-key, \Latitude\QueryBuilder\CriteriaInterface> $criteria
     *
     * @psalm-return Promise<array|null>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\OneResultExpected
     */
    public function findOneBy(array $criteria): Promise;

    /**
     * Search for a collection by specified conditions
     *
     * @psalm-param array<array-key, \Latitude\QueryBuilder\CriteriaInterface> $criteria
     * @psalm-param positive-int|null                                          $limit
     * @psalm-param array<non-empty-string, non-empty-string>|null             $orderBy
     *
     * @psalm-return Promise<array|null>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\ResultSetIterationFailed
     */
    public function find(array $criteria, ?int $offset, ?int $limit = null, ?array $orderBy = null): Promise;
}
