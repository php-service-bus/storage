<?php

/**
 * Common storage parts.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Common;

use Amp\Promise;

/**
 * Interface adapter for working with the database.
 */
interface DatabaseAdapter extends QueryExecutor, BinaryDataDecoder
{
    /**
     * Start transaction.
     *
     * @return Promise<\ServiceBus\Storage\Common\Transaction>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    public function transaction(): Promise;

    /**
     * Executes a function in a transaction.
     *
     * @psalm-param callable(\ServiceBus\Storage\Common\QueryExecutor):\Generator<void> $function
     *
     * @return Promise<void>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    public function transactional(callable $function): Promise;
}
