<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\Sql\DoctrineDBAL;

use Doctrine\DBAL\Exception as DoctrineDBALExceptions;
use ServiceBus\Storage\Common\Exceptions as InternalExceptions;
use ServiceBus\Storage\Common\StorageConfiguration;

/**
 * Convert Doctrine DBAL exceptions.
 *
 * @internal
 *
 * @return InternalExceptions\ConnectionFailed|InternalExceptions\StorageInteractingFailed|InternalExceptions\UniqueConstraintViolationCheckFailed
 */
function adaptDbalThrowable(\Throwable $throwable): \Exception
{
    $message = \str_replace(\PHP_EOL, '', $throwable->getMessage());

    if ($throwable instanceof DoctrineDBALExceptions\ConnectionException)
    {
        return new InternalExceptions\ConnectionFailed($message, (int) $throwable->getCode(), $throwable);
    }

    if ($throwable instanceof DoctrineDBALExceptions\UniqueConstraintViolationException)
    {
        return new InternalExceptions\UniqueConstraintViolationCheckFailed($message, (int) $throwable->getCode(), $throwable);
    }

    return new InternalExceptions\StorageInteractingFailed($message, (int) $throwable->getCode(), $throwable);
}

/**
 * @internal
 */
function inMemoryAdapter(): DoctrineDBALAdapter
{
    return new DoctrineDBALAdapter(
        new StorageConfiguration('sqlite:///:memory:')
    );
}
