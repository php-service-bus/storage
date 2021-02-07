<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * Common storage parts.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Tests\Common;

use PHPUnit\Framework\TestCase;
use ServiceBus\Storage\Common\StorageConfiguration;

/**
 *
 */
final class StorageConfigurationTest extends TestCase
{
    /**
     * @test
     */
    public function parseSqlite(): void
    {
        $configuration = new StorageConfiguration('sqlite:///:memory:');

        self::assertSame('sqlite:///:memory:', $configuration->originalDSN);
        self::assertSame('sqlite', $configuration->scheme);
        self::assertSame('localhost', $configuration->host);
        self::assertSame(':memory:', $configuration->databaseName);
        self::assertSame('UTF-8', $configuration->encoding);
    }

    /**
     * @test
     */
    public function parseFullDSN(): void
    {
        $configuration = new StorageConfiguration(
            'pgsql://someUser:someUserPassword@host:54332/databaseName?charset=UTF-16'
        );

        self::assertSame('pgsql', $configuration->scheme);
        self::assertSame('host', $configuration->host);
        self::assertSame(54332, $configuration->port);
        self::assertSame('databaseName', $configuration->databaseName);
        self::assertSame('UTF-16', $configuration->encoding);
        self::assertSame('someUser', $configuration->username);
        self::assertSame('someUserPassword', $configuration->password);
    }

    /**
     * @test
     */
    public function parseWithoutPassword(): void
    {
        $configuration = new StorageConfiguration('pgsql://username:@localhost:5432/databaseName');

        self::assertSame('pgsql', $configuration->scheme);
        self::assertSame('localhost', $configuration->host);
        self::assertSame(5432, $configuration->port);
        self::assertSame('databaseName', $configuration->databaseName);
        self::assertSame('UTF-8', $configuration->encoding);
        self::assertSame('username', $configuration->username);
        self::assertNull($configuration->password);
    }
}
