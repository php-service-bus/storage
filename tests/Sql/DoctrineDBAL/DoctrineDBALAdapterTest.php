<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Tests\Sql\DoctrineDBAL;

use Amp\Loop;
use function Amp\Promise\wait;
use function ServiceBus\Storage\Sql\DoctrineDBAL\inMemoryAdapter;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\ConnectionFailed;
use ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed;
use ServiceBus\Storage\Common\StorageConfiguration;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use ServiceBus\Storage\Tests\Sql\BaseStorageAdapterTest;

/**
 *
 */
final class DoctrineDBALAdapterTest extends BaseStorageAdapterTest
{
    /** @var DoctrineDBALAdapter|null */
    private static $adapter = null;

    /**
     * {@inheritdoc}
     */
    protected static function getAdapter(): DatabaseAdapter
    {
        if (false === isset(self::$adapter))
        {
            self::$adapter = inMemoryAdapter();
        }

        return self::$adapter;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    protected function setUp(): void
    {
        parent::setUp();

        wait(
            static::getAdapter()->execute(
                'CREATE TABLE IF NOT EXISTS test_ai (id serial PRIMARY KEY, value VARCHAR)'
            )
        );
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
                $result = yield $adapter->execute('INSERT INTO test_ai (value) VALUES (\'qwerty\')');

                static::assertSame('1', yield $result->lastInsertId());

                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield $adapter->execute('INSERT INTO test_ai (value) VALUES (\'qwerty\')');

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
                $adapter = new DoctrineDBALAdapter(
                    new StorageConfiguration('pgsql://localhost:4486/foo?charset=UTF-8')
                );

                yield $adapter->execute('SELECT now()');
            }
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function failedConnectionString(): void
    {
        $this->expectException(StorageInteractingFailed::class);

        Loop::run(
            static function (): \Generator
            {
                $adapter = new DoctrineDBALAdapter(
                    new StorageConfiguration('')
                );

                yield $adapter->execute('SELECT now()');
            }
        );
    }
}
