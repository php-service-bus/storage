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

namespace ServiceBus\Storage\Tests\Sql;

use Amp\Loop;
use Amp\Promise;
use PHPUnit\Framework\TestCase;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\OneResultExpected;
use ServiceBus\Storage\Common\Exceptions\StorageInteractingFailed;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;
use function Amp\Promise\wait;
use function Latitude\QueryBuilder\criteria;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\find;
use function ServiceBus\Storage\Sql\remove;
use function ServiceBus\Storage\Sql\unescapeBinary;

/**
 *
 */
abstract class BaseStorageAdapterTest extends TestCase
{
    /**
     * Get database adapter.
     */
    abstract protected static function getAdapter(): DatabaseAdapter;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $adapter = static::getAdapter();

        wait(
            $adapter->execute(
                'CREATE TABLE IF NOT EXISTS storage_test_table (id UUID, identifier_class VARCHAR NOT NULL, payload BYTEA null , CONSTRAINT identifier PRIMARY KEY (id, identifier_class))'
            )
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $adapter = static::getAdapter();

        wait($adapter->execute('DELETE FROM storage_test_table'));
    }

    /**
     * @test
     */
    public function unescapeBinary(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                $data = \sha1(\random_bytes(256));

                yield $adapter->execute(
                    'INSERT INTO storage_test_table (id, identifier_class, payload) VALUES (?, ?, ?), (?, ?, ?)',
                    [
                        '77961031-fd0f-4946-b439-dfc2902b961a', 'SomeIdentifierClass', $data,
                        '81c3f1d1-1f75-478e-8bc6-2bb02cd381be', 'SomeIdentifierClass2', \sha1(\random_bytes(256)),
                    ]
                );

                /** @var \ServiceBus\Storage\Common\ResultSet $iterator */
                $iterator = yield find(
                    queryExecutor: $adapter,
                    tableName: 'storage_test_table',
                    criteria: [equalsCriteria('id', '77961031-fd0f-4946-b439-dfc2902b961a')]
                );

                $result = yield fetchAll($iterator);

                static::assertCount(1, $result);
                static::assertSame($data, unescapeBinary($adapter, $result[0]['payload']));
            }
        );
    }

    /**
     * @test
     */
    public function resultSet(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                $data = \sha1(\random_bytes(256));

                yield $adapter->execute(
                    'INSERT INTO storage_test_table (id, identifier_class, payload) VALUES (?, ?, ?), (?, ?, ?)',
                    [
                        '77961031-fd0f-4946-b439-dfc2902b961a', 'SomeIdentifierClass', $data,
                        '81c3f1d1-1f75-478e-8bc6-2bb02cd381be', 'SomeIdentifierClass2', \sha1(\random_bytes(256)),
                    ]
                );

                /** @var \ServiceBus\Storage\Common\ResultSet $iterator */
                $iterator = yield find(
                    queryExecutor: $adapter,
                    tableName: 'storage_test_table',
                    criteria: [equalsCriteria('id', '77961031-fd0f-4946-b439-dfc2902b961a')]
                );

                $result = yield fetchAll($iterator);

                static::assertCount(1, $result);
                static::assertSame($data, unescapeBinary($adapter, $result[0]['payload']));
            }
        );
    }

    /**
     * @test
     */
    public function emptyResultSet(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                $iterator = yield $adapter->execute('SELECT * from storage_test_table');
                $result   = yield fetchAll($iterator);

                static::assertEmpty($result);
            }
        );
    }

    /**
     * @test
     */
    public function failedQuery(): void
    {
        $this->expectException(StorageInteractingFailed::class);

        Loop::run(
            static function (): \Generator
            {
                yield find(
                    queryExecutor: static::getAdapter(),
                    tableName: 'asegfseg'
                );
            }
        );
    }

    /**
     * @test
     */
    public function findOne(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                yield self::importFixtures($adapter);

                /** @var \ServiceBus\Storage\Common\ResultSet $iterator */
                $iterator = yield $adapter->execute(
                    'SELECT * from storage_test_table WHERE identifier_class = ?',
                    ['SomeIdentifierClass2']
                );

                $result = yield fetchOne($iterator);

                static::assertArrayHasKey('identifier_class', $result);
                static::assertSame('SomeIdentifierClass2', $result['identifier_class']);
            }
        );
    }

    /**
     * @test
     */
    public function findOneWhenEmptySet(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                /** @var \ServiceBus\Storage\Common\ResultSet $iterator */
                $iterator = yield $adapter->execute(
                    'SELECT * from storage_test_table WHERE identifier_class = ?',
                    ['SomeIdentifierClass2']
                );

                $result = yield fetchOne($iterator);

                static::assertEmpty($result);
            }
        );
    }

    /**
     * @test
     */
    public function findOneWhenWrongSet(): void
    {
        $this->expectException(OneResultExpected::class);
        $this->expectExceptionMessage('A single record was requested, but the result of the query execution contains several ("2")');

        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                yield self::importFixtures($adapter);

                /** @var \ServiceBus\Storage\Common\ResultSet $iterator */
                $iterator = yield $adapter->execute('SELECT * from storage_test_table');

                yield fetchOne($iterator);
            }
        );
    }

    /**
     * @test
     */
    public function uniqueKeyCheckFailed(): void
    {
        $this->expectException(UniqueConstraintViolationCheckFailed::class);

        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                yield $adapter->execute(
                    'INSERT INTO storage_test_table (id, identifier_class) VALUES (?, ?), (?, ?)',
                    [
                        '77961031-fd0f-4946-b439-dfc2902b961a', 'SomeIdentifierClass',
                        '77961031-fd0f-4946-b439-dfc2902b961a', 'SomeIdentifierClass',
                    ]
                );
            }
        );
    }

    /**
     * @test
     */
    public function rowsCount(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield $adapter->execute(
                    'INSERT INTO storage_test_table (id, identifier_class) VALUES (?, ?), (?, ?)',
                    [
                        '77961031-fd0f-4946-b439-dfc2902b961a', 'SomeIdentifierClass',
                        '77961031-fd0f-4946-b439-dfc2902b961d', 'SomeIdentifierClass',
                    ]
                );

                static::assertSame(2, $result->affectedRows());

                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield $adapter->execute(
                    'DELETE FROM storage_test_table where id = \'77961031-fd0f-4946-b439-dfc2902b961d\''
                );

                static::assertSame(1, $result->affectedRows());

                yield remove($adapter, 'storage_test_table');

                /** @var int $result */
                $result = yield remove($adapter, 'storage_test_table');

                static::assertSame(0, $result);

                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield $adapter->execute(
                    'SELECT * FROM storage_test_table where id = \'77961031-fd0f-4946-b439-dfc2902b961d\''
                );

                static::assertSame(0, $result->affectedRows());
            }
        );
    }

    private static function importFixtures(DatabaseAdapter $adapter): Promise
    {
        return $adapter->execute(
            'INSERT INTO storage_test_table (id, identifier_class) VALUES (?, ?), (?, ?)',
            [
                '77961031-fd0f-4946-b439-dfc2902b961a', 'SomeIdentifierClass',
                '81c3f1d1-1f75-478e-8bc6-2bb02cd381be', 'SomeIdentifierClass2',
            ]
        );
    }
}
