<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Tests\Sql;

use Amp\Loop;
use function Amp\Promise\wait;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\fetchAll;
use function ServiceBus\Storage\Sql\fetchOne;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\selectQuery;
use PHPUnit\Framework\Constraint\IsType;
use PHPUnit\Framework\TestCase;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Common\Exceptions\UniqueConstraintViolationCheckFailed;
use ServiceBus\Storage\Common\QueryExecutor;
use ServiceBus\Storage\Common\Transaction;

/**
 *
 */
abstract class BaseTransactionTest extends TestCase
{
    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $adapter = static::getAdapter();

        wait(
            $adapter->execute('DELETE FROM test_result_set')
        );
    }

    /**
     * Get database adapter.
     */
    abstract protected static function getAdapter(): DatabaseAdapter;

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function simpleTransaction(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                /** @var \ServiceBus\Storage\Common\Transaction $transaction */
                $transaction = yield  $adapter->transaction();

                yield $transaction->execute(
                    'INSERT INTO test_result_set (id, value) VALUES (?,?), (?,?)',
                    [
                        'c072f311-4a0f-4d53-91ea-575b96706eeb', 'value1',
                        '0e6007d9-5386-40ae-a05c-9decec172d60', 'value2',
                    ]
                );

                yield $transaction->commit();

                $result = yield fetchAll(
                    yield $adapter->execute('SELECT * FROM test_result_set')
                );

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
    public function successTransactional(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                yield $adapter->transactional(
                    static function (QueryExecutor $executor): \Generator
                    {
                        yield $executor->execute(
                            'INSERT INTO test_result_set (id, value) VALUES (?,?), (?,?)',
                            [
                                'c072f311-4a0f-4d53-91ea-575b96706eeb', 'value1',
                                '0e6007d9-5386-40ae-a05c-9decec172d60', 'value2',
                            ]
                        );
                    }
                );

                $result = yield fetchAll(yield $adapter->execute('SELECT * FROM test_result_set'));

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
    public function transactionWithReadData(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                $uuid = 'cb9f20de-6a8e-4934-84b4-71da78e42697';

                $query = insertQuery('test_result_set', ['id' => $uuid, 'value' => 'value2'])->compile();

                yield $adapter->execute($query->sql(), $query->params());

                /** @var \ServiceBus\Storage\Common\Transaction $transaction */
                $transaction = yield $adapter->transaction();

                $query = selectQuery('test_result_set')
                    ->where(equalsCriteria('id', $uuid))
                    ->compile();

                $someReadData = yield fetchOne(yield $transaction->execute($query->sql(), $query->params()));

                static::assertNotEmpty($someReadData);
                static::assertCount(2, $someReadData);

                yield $transaction->commit();
            }
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function rollback(): void
    {
        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                /** @var \ServiceBus\Storage\Common\Transaction $transaction */
                $transaction = yield $adapter->transaction();

                $query = insertQuery(
                    'test_result_set',
                    ['id' => 'bd561cb9-e745-41fc-9de6-1f41f0665063', 'value' => 'value2']
                )->compile();

                yield $transaction->execute($query->sql(), $query->params());
                yield $transaction->rollback();

                $query = selectQuery('test_result_set')->compile();

                /** @var array $collection */
                $collection = yield fetchAll(yield$adapter->execute($query->sql(), $query->params()));

                static::assertThat($collection, new IsType('array'));
                static::assertCount(0, $collection);
            }
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function transactionalWithDuplicate(): void
    {
        $this->expectException(UniqueConstraintViolationCheckFailed::class);

        Loop::run(
            static function (): \Generator
            {
                $adapter = static::getAdapter();

                yield $adapter->transactional(
                    static function (Transaction $transaction): \Generator
                    {
                        $uuid = 'cb9f20de-6a8e-4934-84b4-71da78e42697';

                        $query = insertQuery('test_result_set', ['id' => $uuid, 'value' => 'value2'])->compile();

                        yield $transaction->execute($query->sql(), $query->params());
                        yield $transaction->execute($query->sql(), $query->params());
                    }
                );
            }
        );
    }
}
