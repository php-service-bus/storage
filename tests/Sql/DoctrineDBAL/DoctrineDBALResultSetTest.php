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
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\fetchOne;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;

/**
 *
 */
final class DoctrineDBALResultSetTest extends TestCase
{
    /** @var DoctrineDBALAdapter */
    private $adapter;

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
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

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        wait(
            $this->adapter->execute('DROP TABLE test_result_set')
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
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

                static::assertNotEmpty($result);
                static:: assertSame(['id' => 'uuid2', 'value' => 'value2'], $result);

                $result = yield fetchOne(
                    yield $this->adapter->execute('SELECT * FROM test_result_set WHERE id = \'uuid4\'')
                );

                static::assertNull($result);
            }
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
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

                static::assertNotEmpty($result);
                static::assertCount(2, $result);
            }
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function fetchAllWithEmptySet(): void
    {
        Loop::run(
            function (): \Generator
            {
                $result = yield fetchAll(yield $this->adapter->execute('SELECT * FROM test_result_set'));

                static::assertThat($result, new IsType('array'));
                static::assertEmpty($result);
            }
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
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

                    static::assertSame($row, $rowCopy);
                }
            }
        );
    }
}
