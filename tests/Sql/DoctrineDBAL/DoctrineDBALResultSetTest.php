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

namespace ServiceBus\Storage\Tests\Sql\DoctrineDBAL;

use Amp\Loop;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use function Amp\Promise\wait;
use function ServiceBus\Storage\Sql\DoctrineDBAL\inMemoryAdapter;
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\fetchOne;

/**
 * @group inmemory
 */
final class DoctrineDBALResultSetTest extends TestCase
{
    /**
     * @var DoctrineDBALAdapter
     */
    private $adapter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapter = inMemoryAdapter();

        wait(
            $this->adapter->execute(
                'CREATE TABLE IF NOT EXISTS test_result_set (id varchar PRIMARY KEY, value VARCHAR)'
            )
        );
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        wait(
            $this->adapter->execute('DROP TABLE test_result_set')
        );
    }

    /**
     * @test
     */
    public function fetchOne(): void
    {
        Loop::run(
            function (): \Generator
            {
                yield $this->adapter->execute(
                    'INSERT INTO test_result_set (id, value) VALUES (?,?), (?,?)',
                    [
                        'uuid1', 'value1',
                        'uuid2', 'value2',
                    ]
                );

                $result = yield fetchOne(
                    yield  $this->adapter->execute('SELECT * FROM test_result_set WHERE id = \'uuid2\'')
                );

                self::assertNotEmpty($result);
                self::assertSame(['id' => 'uuid2', 'value' => 'value2'], $result);

                $result = yield fetchOne(
                    yield $this->adapter->execute('SELECT * FROM test_result_set WHERE id = \'uuid4\'')
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
            function (): \Generator
            {
                yield $this->adapter->execute(
                    'INSERT INTO test_result_set (id, value) VALUES (?,?), (?,?)',
                    [
                        'uuid1', 'value1',
                        'uuid2', 'value2',
                    ]
                );

                $result = yield fetchAll(yield $this->adapter->execute('SELECT * FROM test_result_set'));

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
            function (): \Generator
            {
                $result = yield fetchAll(yield $this->adapter->execute('SELECT * FROM test_result_set'));

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
            function (): \Generator
            {
                yield $this->adapter->execute(
                    'INSERT INTO test_result_set (id, value) VALUES (?,?), (?,?)',
                    [
                        'uuid1', 'value1',
                        'uuid2', 'value2',
                    ]
                );

                /** @var \ServiceBus\Storage\Common\ResultSet $result */
                $result = yield $this->adapter->execute('SELECT * FROM test_result_set');

                while (yield $result->advance())
                {
                    $row     = $result->getCurrent();
                    $rowCopy = $result->getCurrent();

                    self::assertSame($row, $rowCopy);
                }
            }
        );
    }
}
