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
use Doctrine\DBAL\DriverManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions;
use ServiceBus\Storage\Common\StorageConfiguration;
use function Amp\call;

/**
 * DoctrineDBAL adapter.
 * Designed primarily for testing. Please do not use this adapter in your code
 *
 * @internal
 */
final class DoctrineDBALAdapter implements DatabaseAdapter
{
    /**
     * @var StorageConfiguration StorageConfiguration
     */
    private $configuration;

    /**
     * @var Connection|null
     */
    private $connection;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(StorageConfiguration $configuration, LoggerInterface $logger = null)
    {
        $this->configuration = $configuration;
        $this->logger        = $logger ?? new NullLogger();
    }

    public function execute(string $queryString, array $parameters = []): Promise
    {
        $this->logger->debug($queryString, $parameters);

        try
        {
            $statement = $this->connection()->prepare($queryString);
            $result    = $statement->executeQuery($parameters);

            return new Success(new DoctrineDBALResultSet($this->connection(), $result));
        }
        catch (\Throwable $throwable)
        {
            /** @noinspection PhpUnhandledExceptionInspection */
            throw adaptDbalThrowable($throwable);
        }
    }

    public function transactional(callable $function): Promise
    {
        return call(
            function () use ($function): \Generator
            {
                /** @var \ServiceBus\Storage\Common\Transaction $transaction */
                $transaction = yield $this->transaction();

                try
                {
                    /** @var \Generator $generator */
                    $generator = $function($transaction);

                    yield from $generator;

                    yield $transaction->commit();
                }
                catch (\Throwable $throwable)
                {
                    yield $transaction->rollback();

                    throw $throwable;
                }
                finally
                {
                    unset($transaction);
                }
            }
        );
    }

    public function transaction(): Promise
    {
        try
        {
            $this->logger->debug('START TRANSACTION');

            $this->connection()->beginTransaction();

            return new Success(new DoctrineDBALTransaction($this->connection(), $this->logger));
        }
        // @codeCoverageIgnoreStart
        catch (\Throwable $exception)
        {
            /** @noinspection PhpUnhandledExceptionInspection */
            throw adaptDbalThrowable($exception);
        }
        // @codeCoverageIgnoreEnd
    }

    public function unescapeBinary($payload): string
    {
        /** @var resource|string $payload */
        if (\is_resource($payload))
        {
            $result = \stream_get_contents($payload, -1, 0);

            if ($result !== false)
            {
                return $result;
            }
        }

        return (string) $payload;
    }

    /**
     * Get connection instance.
     *
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     */
    private function connection(): Connection
    {
        if ($this->connection === null)
        {
            try
            {
                $this->connection = DriverManager::getConnection(['url' => $this->configuration->originalDSN]);
            }
            catch (\Throwable $throwable)
            {
                throw new InvalidConfigurationOptions($throwable->getMessage(), (int) $throwable->getCode(), $throwable);
            }
        }

        return $this->connection;
    }
}
