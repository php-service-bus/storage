<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Sql\Migration;

use Amp\Promise;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ServiceBus\Storage\Common\DatabaseAdapter;
use function Amp\call;
use function ServiceBus\Common\invokeReflectionMethod;
use function ServiceBus\Common\readReflectionPropertyValue;

/**
 * Migrations executor
 */
final class SqlMigrationProcessor
{
    private const DIRECTION_UP   = 'up';
    private const DIRECTION_DOWN = 'down';

    /**
     * @var DatabaseAdapter
     */
    private $storage;

    /** @var SqlMigrationLoader */
    private $migrationsLoader;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(DatabaseAdapter $storage, SqlMigrationLoader $migrationsLoader, ?LoggerInterface $logger = null)
    {
        $this->storage          = $storage;
        $this->migrationsLoader = $migrationsLoader;
        $this->logger           = $logger ?? new NullLogger();
    }

    /**
     * @return Promise<int>
     *
     * @throws \RuntimeException Incorrect migration file
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    public function up(): Promise
    {
        return $this->process(self::DIRECTION_UP);
    }

    /**
     * @return Promise<int>
     *
     * @throws \RuntimeException Incorrect migration file
     * @throws \ServiceBus\Storage\Common\Exceptions\InvalidConfigurationOptions
     * @throws \ServiceBus\Storage\Common\Exceptions\ConnectionFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed
     * @throws \ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed
     */
    public function down(): Promise
    {
        return $this->process(self::DIRECTION_DOWN);
    }

    /**
     * Performing migrations in a given direction (up / down)
     *
     * @return Promise<int>
     */
    private function process(string $direction): Promise
    {
        return call(
            function () use ($direction): \Generator
            {
                /** @var \ServiceBus\Storage\Common\Transaction $transaction */
                $transaction = yield $this->storage->transaction();

                try
                {
                    $executedQueries = 0;

                    /**
                     * @psalm-var array<string, \ServiceBus\Storage\Sql\Migration\Migration> $migrations
                     */
                    $migrations = yield $this->migrationsLoader->load();

                    yield $transaction->execute(
                        'CREATE TABLE IF NOT EXISTS migration ( version varchar NOT NULL );'
                    );

                    /**
                     * @var string    $version
                     * @var Migration $migration
                     */
                    foreach ($migrations as $version => $migration)
                    {
                        /**
                         * @var \ServiceBus\Storage\Common\ResultSet $resultSet
                         */
                        $resultSet = yield $transaction->execute(
                            'INSERT INTO migration (version) VALUES (?) ON CONFLICT DO NOTHING',
                            [$version]
                        );

                        /** Миграция была добавлена ранее */
                        if ($resultSet->affectedRows() === 0)
                        {
                            $this->logger->debug('Skip "{version}" migration', ['version' => $version]);

                            continue;
                        }

                        invokeReflectionMethod($migration, $direction);

                        /** @var string[] $queries */
                        $queries = readReflectionPropertyValue($migration, 'queries');

                        /** @var array $parameters */
                        $parameters = readReflectionPropertyValue($migration, 'params');

                        foreach ($queries as $query)
                        {
                            /** @psalm-var array<array-key, string|int|float|null> $queryParameters */
                            $queryParameters = $parameters[\sha1($query)] ?? [];

                            yield $transaction->execute($query, $queryParameters);

                            $executedQueries++;
                        }
                    }

                    yield $transaction->commit();

                    return $executedQueries;
                }
                catch (\Throwable $throwable)
                {
                    yield $transaction->rollback();

                    throw $throwable;
                }
            }
        );
    }
}
