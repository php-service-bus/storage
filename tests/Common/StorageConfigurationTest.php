<?php

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
    /** @test */
    public function parseSqlite(): void
    {
        $configuration = new StorageConfiguration('sqlite:///:memory:');

        static::assertSame('sqlite:///:memory:', $configuration->originalDSN);
        static::assertSame('sqlite', $configuration->scheme);
        static::assertSame('localhost', $configuration->host);
        static::assertSame(':memory:', $configuration->databaseName);
        static::assertSame('UTF-8', $configuration->encoding);
    }

    /** @test */
    public function parseFullDSN(): void
    {
        $configuration = new StorageConfiguration(
            'pgsql://someUser:someUserPassword@host:54332/databaseName?charset=UTF-16'
        );

        static::assertSame('pgsql', $configuration->scheme);
        static::assertSame('host', $configuration->host);
        static::assertSame(54332, $configuration->port);
        static::assertSame('databaseName', $configuration->databaseName);
        static::assertSame('UTF-16', $configuration->encoding);
        static::assertSame('someUser', $configuration->username);
        static::assertSame('someUserPassword', $configuration->password);
    }

    /** @test */
    public function parseWithoutPassword(): void
    {
        $configuration = new StorageConfiguration('pgsql://username:@localhost:5432/databaseName');

        static::assertSame('pgsql', $configuration->scheme);
        static::assertSame('localhost', $configuration->host);
        static::assertSame(5432, $configuration->port);
        static::assertSame('databaseName', $configuration->databaseName);
        static::assertSame('UTF-8', $configuration->encoding);
        static::assertSame('username', $configuration->username);
        static::assertNull($configuration->password);
    }
}
