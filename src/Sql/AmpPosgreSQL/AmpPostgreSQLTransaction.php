<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Sql\AmpPosgreSQL;

use Amp\Postgres\PgSqlCommandResult;
use Amp\Postgres\PooledResultSet;
use Amp\Postgres\PqCommandResult;
use Amp\Sql\ResultSet as AmpResultSet;
use function Amp\call;
use Amp\Postgres\Transaction as AmpTransaction;
use Amp\Promise;
use Psr\Log\LoggerInterface;
use ServiceBus\Storage\Common\Transaction;

/**
 * Async PostgreSQL transaction adapter.
 *
 * @internal
 */
final class AmpPostgreSQLTransaction implements Transaction
{
    /** @var AmpTransaction */
    private $transaction;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(AmpTransaction $transaction, LoggerInterface $logger)
    {
        $this->transaction = $transaction;
        $this->logger      = $logger;
    }

    public function __destruct()
    {
        if ($this->transaction->isAlive())
        {
            $this->transaction->close();
        }
    }

    public function execute(string $queryString, array $parameters = []): Promise
    {
        return call(
            function () use ($queryString, $parameters): \Generator
            {
                try
                {
                    $this->logger->debug($queryString, $parameters);

                    /** @var AmpResultSet|PgSqlCommandResult|PooledResultSet|PqCommandResult $resultSet */
                    $resultSet = yield $this->transaction->execute($queryString, $parameters);

                    return new AmpPostgreSQLResultSet($resultSet);
                }
                // @codeCoverageIgnoreStart
                catch (\Throwable $throwable)
                {
                    throw adaptAmpThrowable($throwable);
                }
                // @codeCoverageIgnoreEnd
            }
        );
    }

    public function commit(): Promise
    {
        return call(
            function (): \Generator
            {
                try
                {
                    $this->logger->debug('COMMIT');

                    /** @psalm-suppress TooManyTemplateParams */
                    yield $this->transaction->commit();
                }
                // @codeCoverageIgnoreStart
                catch (\Throwable $throwable)
                {
                    throw adaptAmpThrowable($throwable);
                }
                finally
                {
                    $this->transaction->close();
                }
                // @codeCoverageIgnoreEnd
            }
        );
    }

    public function rollback(): Promise
    {
        return call(
            function (): \Generator
            {
                try
                {
                    $this->logger->debug('ROLLBACK');

                    yield $this->transaction->rollback();
                }
                // @codeCoverageIgnoreStart
                catch (\Throwable)
                {
                    /** We will not throw an exception */
                }
                finally
                {
                    $this->transaction->close();
                }
                // @codeCoverageIgnoreEnd
            }
        );
    }

    public function unescapeBinary($payload): string
    {
        if (\is_resource($payload))
        {
            $payload = \stream_get_contents($payload, -1, 0);
        }

        return \pg_unescape_bytea((string) $payload);
    }
}
