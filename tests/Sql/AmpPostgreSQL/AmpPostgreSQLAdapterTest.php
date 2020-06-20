<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Tests\Sql\AmpPostgreSQL;

use Amp\Loop;
use function Amp\Promise\wait;
use function ServiceBus\Storage\Sql\AmpPosgreSQL\postgreSqlAdapterFactory;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\ConnectionFailed;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use ServiceBus\Storage\Tests\Sql\BaseStorageAdapterTest;

/**
 *
 */
final class AmpPostgreSQLAdapterTest extends BaseStorageAdapterTest
{
    /** @var AmpPostgreSQLAdapter|null */
    private static $adapter = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        wait(
            static::getAdapter()->execute(
                'CREATE TABLE IF NOT EXISTS test_ai (id serial PRIMARY KEY, value VARCHAR)'
            )
        );
    }

    /**
     * @throws \Throwable
     *
     */
    public static function tearDownAfterClass(): void
    {
        $adapter = static::getAdapter();

        try
        {
            wait($adapter->execute('DROP TABLE storage_test_table'));
            wait($adapter->execute('DROP TABLE test_ai'));
        }
        catch (\Throwable $throwable)
        {
        }
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        $adapter = static::getAdapter();

        wait($adapter->execute('TRUNCATE TABLE test_ai'));

        parent::tearDown();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    protected static function getAdapter(): DatabaseAdapter
    {
        if (false === isset(self::$adapter))
        {
            self::$adapter = postgreSqlAdapterFactory((string) \getenv('TEST_POSTGRES_DSN'));
        }

        return self::$adapter;
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function lastInsertId(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield $adapter->execute('INSERT INTO test_ai (value) VALUES (\'qwerty\') RETURNING id');

                static::assertSame('1', yield $result->lastInsertId());

                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield $adapter->execute('INSERT INTO test_ai (value) VALUES (\'qwerty\') RETURNING id');

                static::assertSame('2', yield $result->lastInsertId());
            }
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function failedConnection(): void
    {
        $this->expectException(ConnectionFailed::class);

        Loop::run(
            static function (): \Generator
            {
                $adapter = new AmpPostgreSQLAdapter(
                    new StorageConfiguration('qwerty')
                );

                yield $adapter->execute('SELECT now()');
            }
        );
    }
}
