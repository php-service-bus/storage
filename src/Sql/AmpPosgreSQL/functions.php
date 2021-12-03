<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\Sql\AmpPosgreSQL;

use Amp\Postgres\QueryExecutionError;
use Amp\Sql\ConnectionException;
use ServiceBus\Storage\Common\Exceptions as InternalExceptions;
use ServiceBus\Storage\Common\StorageConfiguration;

/**
 * Convert AmPHP exceptions.
 *
 * @internal
 *
 * @return InternalExceptions\ConnectionFailed|InternalExceptions\StorageInteractingFailed|InternalExceptions\UniqueConstraintViolationCheckFailed
 */
function adaptAmpThrowable(\Throwable $throwable): \Throwable
{
    if (
        $throwable instanceof QueryExecutionError &&
        \in_array((int) $throwable->getDiagnostics()['sqlstate'], [23503, 23505], true)
    ) {
        /** @psalm-suppress RedundantCast */
        return new InternalExceptions\UniqueConstraintViolationCheckFailed(
            $throwable->getMessage(),
            (int) $throwable->getCode(),
            $throwable
        );
    }

    if ($throwable instanceof ConnectionException)
    {
        return new InternalExceptions\ConnectionFailed(
            $throwable->getMessage(),
            (int) $throwable->getCode(),
            $throwable
        );
    }

    return new InternalExceptions\StorageInteractingFailed(
        $throwable->getMessage(),
        (int) $throwable->getCode(),
        $throwable
    );
}

/**
 * @internal
 *
 * @psalm-param non-empty-string $connectionDsn
 *
 * @throws InternalExceptions\InvalidConfigurationOptions
 */
function postgreSqlAdapterFactory(string $connectionDsn): AmpPostgreSQLAdapter
{
    return new AmpPostgreSQLAdapter(
        new StorageConfiguration($connectionDsn)
    );
}
