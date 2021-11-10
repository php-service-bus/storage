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

use Amp\Promise;
use Amp\Success;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use ServiceBus\Storage\Common\Transaction;
use function Amp\call;

/**
 * @internal
 */
final class DoctrineDBALTransaction implements Transaction
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Connection $connection, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->logger     = $logger;
    }

    public function execute(string $queryString, array $parameters = []): Promise
    {
        $this->logger->debug($queryString, $parameters);

        try
        {
            $statement = $this->connection->prepare($queryString);
            $result = $statement->executeQuery($parameters);

            return new Success(new DoctrineDBALResultSet($this->connection, $result));
        }
        // @codeCoverageIgnoreStart
        catch (\Throwable $throwable)
        {
            /** @noinspection PhpUnhandledExceptionInspection */
            throw adaptDbalThrowable($throwable);
        }
        // @codeCoverageIgnoreEnd
    }

    public function commit(): Promise
    {
        return call(
            function (): void
            {
                try
                {
                    $this->logger->debug('COMMIT');

                    $this->connection->commit();
                }
                // @codeCoverageIgnoreStart
                catch (\Throwable $throwable)
                {
                    throw adaptDbalThrowable($throwable);
                }
                // @codeCoverageIgnoreEnd
            }
        );
    }

    public function rollback(): Promise
    {
        return call(
            function (): void
            {
                try
                {
                    $this->logger->debug('ROLLBACK');

                    $this->connection->rollBack();
                }
                // @codeCoverageIgnoreStart
                catch (\Throwable)
                {
                    /** We will not throw an exception */
                }
                // @codeCoverageIgnoreEnd
            }
        );
    }

    public function unescapeBinary($payload): string
    {
        /** @var resource|string $payload */
        if (\is_resource($payload))
        {
            $result = \stream_get_contents($payload, -1, 0);

            if (false !== $result)
            {
                return $result;
            }
        }

        return (string) $payload;
    }
}
