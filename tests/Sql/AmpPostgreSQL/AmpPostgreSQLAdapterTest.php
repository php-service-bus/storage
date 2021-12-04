<?php

/** @noinspection PhpUnhandledExceptionInspection */

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Storage\Tests\Sql\AmpPostgreSQL;

use Amp\Loop;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\ConnectionFailed;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use ServiceBus\Storage\Tests\Sql\BaseStorageAdapterTest;
use function Amp\Promise\wait;
use function ServiceBus\Storage\Sql\AmpPosgreSQL\postgreSqlAdapterFactory;

/**
 * @group amphp
 */
final class AmpPostgreSQLAdapterTest extends BaseStorageAdapterTest
{
    /**
     * @var AmpPostgreSQLAdapter|null
     */
    private static $adapter;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        wait(
            self::getAdapter()->execute(
                'CREATE TABLE IF NOT EXISTS test_ai (id serial PRIMARY KEY, value VARCHAR)'
            )
        );
    }

    public static function tearDownAfterClass(): void
    {
        $adapter = self::getAdapter();

        try
        {
            wait($adapter->execute('DROP TABLE storage_test_table'));
            wait($adapter->execute('DROP TABLE test_ai'));
        }
        catch (\Throwable)
        {
        }
    }

    protected function tearDown(): void
    {
        $adapter = self::getAdapter();

        wait($adapter->execute('TRUNCATE TABLE test_ai'));

        parent::tearDown();
    }

    protected static function getAdapter(): DatabaseAdapter
    {
        if (isset(self::$adapter) === false)
        {
            self::$adapter = postgreSqlAdapterFactory((string) \getenv('TEST_POSTGRES_DSN'));
        }

        return self::$adapter;
    }

    /**
     * @test
     */
    public function lastInsertId(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = self::getAdapter();

                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield $adapter->execute('INSERT INTO test_ai (value) VALUES (\'qwerty\') RETURNING id');

                self::assertSame('1', yield $result->lastInsertId());

                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield $adapter->execute('INSERT INTO test_ai (value) VALUES (\'qwerty\') RETURNING id');

                self::assertSame('2', yield $result->lastInsertId());
            }
        );
    }

    /**
     * @test
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
