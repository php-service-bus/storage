<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Tests\Sql\DoctrineDBAL;

use function Amp\Promise\wait;
use function ServiceBus\Storage\Sql\DoctrineDBAL\inMemoryAdapter;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use ServiceBus\Storage\Tests\Sql\BaseTransactionTest;

/**
 *
 */
final class DoctrineDBALTransactionTest extends BaseTransactionTest
{
    /** @var DoctrineDBALAdapter|null */
    private static $adapter = null;

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $adapter = static::getAdapter();

        wait(
            $adapter->execute(
                'CREATE TABLE IF NOT EXISTS test_result_set (id uuid PRIMARY KEY, value binary)'
            )
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Throwable
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        $adapter = static::getAdapter();

        wait(
            $adapter->execute('DROP TABLE test_result_set')
        );
    }

    /**
     * @return DatabaseAdapter
     */
    protected static function getAdapter(): DatabaseAdapter
    {
        if (false === isset(self::$adapter))
        {
            self::$adapter = inMemoryAdapter();
        }

        return self::$adapter;
    }
}
