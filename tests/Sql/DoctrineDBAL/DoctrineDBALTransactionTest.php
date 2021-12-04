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

use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Sql\DoctrineDBAL\DoctrineDBALAdapter;
use ServiceBus\Storage\Tests\Sql\BaseTransactionTest;
use function Amp\Promise\wait;
use function ServiceBus\Storage\Sql\DoctrineDBAL\inMemoryAdapter;

/**
 * @group inmemory
 */
final class DoctrineDBALTransactionTest extends BaseTransactionTest
{
    /**
     * @var DoctrineDBALAdapter|null
     */
    private static $adapter;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $adapter = self::getAdapter();

        wait(
            $adapter->execute(
                'CREATE TABLE IF NOT EXISTS test_result_set (id uuid PRIMARY KEY, value binary)'
            )
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        $adapter = self::getAdapter();

        wait(
            $adapter->execute('DROP TABLE test_result_set')
        );
    }

    protected static function getAdapter(): DatabaseAdapter
    {
        if (isset(self::$adapter) === false)
        {
            self::$adapter = inMemoryAdapter();
        }

        return self::$adapter;
    }
}
