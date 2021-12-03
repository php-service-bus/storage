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

use Amp\Iterator;
use Amp\Postgres\PooledResultSet;
use Amp\Postgres\TimeoutConnector;
use Amp\Sql\CommandResult;
use Amp\Coroutine;
use Amp\Postgres\ConnectionConfig;
use Amp\Postgres\Pool;
use Amp\Promise;
use Amp\Sql\Common\RetryConnector;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions;
use ServiceBus\Storage\Common\StorageConfiguration;
use function Amp\call;
use function Amp\Postgres\connector;

/**
 * @see https://github.com/amphp/postgres
 */
final class AmpPostgreSQLAdapter implements DatabaseAdapter
{
    private const DEFAULT_CONNECTION_TIMEOUT = 5000;
    private const MAX_CONNECTION_RETRY_COUNT = 10;

    private const DEFAULT_MAX_CONNECTIONS = 100;
    private const DEFAULT_IDLE_TIMEOUT    = 60;

    /**
     * @var StorageConfiguration StorageConfiguration
     */
    private $configuration;

    /**
     * @var Pool|null
     */
    private $pool;

    /**
     * @var LoggerInterface|NullLogger
     */
    private $logger;

    /**
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     */
    public function __construct(StorageConfiguration $configuration, ?LoggerInterface $logger = null)
    {
        // @codeCoverageIgnoreStart
        if (\extension_loaded('pgsql') === false)
        {
            throw new InvalidConfigurationOptions('ext-pgsql must be installed');
        }
        // @codeCoverageIgnoreEnd

        $this->configuration = $configuration;
        $this->logger        = $logger ?? new NullLogger();
    }

    public function __destruct()
    {
        $this->pool?->close();
    }

    public function execute(string $queryString, array $parameters = []): Promise
    {
        return call(
            function () use ($queryString, $parameters): \Generator
            {
                try
                {
                    $this->logger->debug($queryString, $parameters);

                    /** @var Iterator|CommandResult|PooledResultSet $resultSet */
                    $resultSet = yield $this->pool()->execute($queryString, $parameters);

                    return new AmpPostgreSQLResultSet($resultSet);
                }
                catch (\Throwable $throwable)
                {
                    throw adaptAmpThrowable($throwable);
                }
            }
        );
    }

    public function transactional(callable $function): Promise
    {
        return call(
            function () use ($function): \Generator
            {
                /** @var \Amp\Postgres\Transaction $originalTransaction */
                $originalTransaction = yield $this->pool()->beginTransaction();

                $transaction = new AmpPostgreSQLTransaction($originalTransaction, $this->logger);

                $this->logger->debug('BEGIN TRANSACTION ISOLATION LEVEL READ COMMITTED');

                try
                {
                    /** @var \Generator $generator */
                    $generator = $function($transaction);

                    /** @psalm-suppress MixedArgumentTypeCoercion */
                    yield new Coroutine($generator);

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
        return call(
            function (): \Generator
            {
                try
                {
                    $this->logger->debug('BEGIN TRANSACTION ISOLATION LEVEL READ COMMITTED');

                    /** @var \Amp\Postgres\Transaction $transaction */
                    $transaction = yield $this->pool()->beginTransaction();

                    return new AmpPostgreSQLTransaction($transaction, $this->logger);
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

    public function unescapeBinary($payload): string
    {
        if (\is_resource($payload))
        {
            $payload = \stream_get_contents($payload, -1, 0);
        }

        return \pg_unescape_bytea((string) $payload);
    }

    /**
     * Receive connection pool.
     */
    private function pool(): Pool
    {
        if ($this->pool === null)
        {
            $queryData = $this->configuration->queryParameters;

            $this->pool = new Pool(
                config: new ConnectionConfig(
                    $this->configuration->host,
                    $this->configuration->port ?? ConnectionConfig::DEFAULT_PORT,
                    $this->configuration->username,
                    $this->configuration->password,
                    $this->configuration->databaseName
                ),
                maxConnections: (int) ($queryData['max_connections'] ?? self::DEFAULT_MAX_CONNECTIONS),
                idleTimeout: (int) ($queryData['idle_timeout'] ?? self::DEFAULT_IDLE_TIMEOUT),
                resetConnections: true,
                connector: connector(
                    new RetryConnector(
                        connector: new TimeoutConnector(self::DEFAULT_CONNECTION_TIMEOUT),
                        maxTries: self::MAX_CONNECTION_RETRY_COUNT
                    )
                )
            );
        }

        return $this->pool;
    }
}
