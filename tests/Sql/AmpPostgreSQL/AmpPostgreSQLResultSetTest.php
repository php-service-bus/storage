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
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use ServiceBus\Storage\Sql\AmpPosgreSQL\AmpPostgreSQLAdapter;
use function Amp\Promise\wait;
use function ServiceBus\Storage\Sql\AmpPosgreSQL\postgreSqlAdapterFactory;
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\fetchOne;

/**
 *
 */
final class AmpPostgreSQLResultSetTest extends TestCase
{
    /**
     * @var AmpPostgreSQLAdapter|null
     */
    private static $adapter;

    public static function setUpBeforeClass(): void
    {
        self::$adapter = postgreSqlAdapterFactory((string) \getenv('TEST_POSTGRES_DSN'));

        wait(
            self::$adapter->execute(
                'CREATE TABLE IF NOT EXISTS test_result_set (id uuid PRIMARY KEY, value VARCHAR)'
            )
        );

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        try
        {
            wait(
                self::$adapter->execute('DROP TABLE test_result_set')
            );
        }
        catch (\Throwable)
        {
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        wait(
            self::$adapter->execute('DELETE FROM test_result_set')
        );
    }

    /**
     * @test
     */
    public function fetchOne(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $uuid1 = '3b5f80dd-0d14-4f8e-9684-0320dc35d3fd';
                $uuid2 = 'ad1278ad-031a-45e0-aa04-2a03e143d438';

                yield self::$adapter->execute(
                    'INSERT INTO test_result_set (id, value) VALUES (?,?), (?,?)',
                    [
                        $uuid1, 'value1',
                        $uuid2, 'value2',
                    ]
                );

                $result = yield fetchOne(
                    yield self::$adapter->execute(
                        \sprintf('SELECT * FROM test_result_set WHERE id = \'%s\'', $uuid2)
                    )
                );

                self::assertNotEmpty($result);
                self:: assertSame(['id' => $uuid2, 'value' => 'value2'], $result);

                $result = yield fetchOne(
                    yield self::$adapter->execute(
                        'SELECT * FROM test_result_set WHERE id = \'b4141f6e-a461-11e8-98d0-529269fb1459\''
                    )
                );

                self::assertNull($result);
            }
        );
    }

    /**
     * @test
     */
    public function fetchAll(): void
    {
        Loop::run(
            static function (): \Generator
            {
                yield self::$adapter->execute(
                    'INSERT INTO test_result_set (id, value) VALUES (?,?), (?,?)',
                    [
                        'b922bda9-d2e5-4b41-b30d-e3b9a3717753', 'value1',
                        '3fdbbc08-c6bd-4fd9-b343-1c069c0d3044', 'value2',
                    ]
                );

                $result = yield fetchAll(yield self::$adapter->execute('SELECT * FROM test_result_set'));

                self::assertNotEmpty($result);
                self::assertCount(2, $result);
            }
        );
    }

    /**
     * @test
     */
    public function fetchAllWithEmptySet(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $result = yield fetchAll(yield self::$adapter->execute('SELECT * FROM test_result_set'));

                self::assertThat($result, new IsType('array'));
                self::assertEmpty($result);
            }
        );
    }

    /**
     * @test
     */
    public function multipleGetCurrentRow(): void
    {
        Loop::run(
            static function (): \Generator
            {
                yield self::$adapter->execute(
                    'INSERT INTO test_result_set (id, value) VALUES (?,?), (?,?)',
                    [
                        '457e634c-6fef-4144-a5e4-76def3f51c10', 'value1',
                        'f4edd226-6fbf-499d-b6c4-b419560a7291', 'value2',
                    ]
                );

                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield self::$adapter->execute('SELECT * FROM test_result_set');

                while (yield $result->advance())
                {
                    $row     = $result->getCurrent();
                    $rowCopy = $result->getCurrent();

                    self::assertSame($row, $rowCopy);
                }
            }
        );
    }

    /**
     * @test
     */
    public function executeCommand(): void
    {
        Loop::run(
            static function (): \Generator
            {
                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield self::$adapter->execute('DELETE FROM test_result_set');

                while (yield $result->advance())
                {
                    self::fail('Non empty cycle');
                }

                self::assertNull(yield $result->lastInsertId());
            }
        );
    }
}
