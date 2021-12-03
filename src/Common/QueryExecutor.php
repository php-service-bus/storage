<?php

/**
 * SQL database adapter implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\Common;

use Amp\Promise;

/**
 * Query execution interface.
 */
interface QueryExecutor
{
    /**
     * Execute query.
     *
     * @psalm-param non-empty-string $queryString
     * @psalm-param array<array-key, string|int|float|null> $parameters
     *
     * @psalm-return Promise<\ServiceBus\Storage\Common\ResultSet>
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    public function execute(string $queryString, array $parameters = []): Promise;
}
