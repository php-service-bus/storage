<?php

/** @noinspection PhpUnhandledExceptionInspection */

/**
 * SQL adapters support module.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=1);

namespace ServiceBus\Storage\Tests\Sql\Finder;

use Amp\Loop;
use PHPUnit\Framework\TestCase;
use ServiceBus\Cache\InMemory\InMemoryStorage;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Sql\Finder\CachedSqlFinder;
use function Amp\Promise\wait;
use function ServiceBus\Common\uuid;
use function ServiceBus\Storage\Sql\DoctrineDBAL\inMemoryAdapter;

/**
 *
 */
final class CachedSqlFinderTest extends TestCase
{
    /**
     * @var DatabaseAdapter
     */
    private static $adapter;

    public static function setUpBeforeClass(): void
    {
        self::$adapter = inMemoryAdapter();

        wait(self::$adapter->execute('CREATE TABLE qwerty (id uuid PRIMARY KEY, title varchar NOT NULL)'));

        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        self::$adapter = null;

        /** @noinspection PhpInternalEntityUsedInspection */
        InMemoryStorage::instance()->clear();

        parent::tearDownAfterClass();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        wait(self::$adapter->execute('DELETE FROM qwerty'));

        /** @noinspection PhpInternalEntityUsedInspection */
        InMemoryStorage::instance()->clear();
    }

    /**
     * @test
     */
    public function selectOne(): void
    {
        Loop::run(
            static function (): \Generator
            {
                yield self::$adapter->execute(
                    'INSERT INTO qwerty(id, title) VALUES(?,?), (?,?)',
                    [
                        '28a3e51c-4944-44a7-bb79-b00fe38d9f0c', 'test1', '98c0ddc1-283b-4787-80ff-d706397922d9', 'test2',
                    ]
                );

                $finder = new CachedSqlFinder('qwerty', self::$adapter);

                /** @var array $entry */
                $entry = yield $finder->findOneById('28a3e51c-4944-44a7-bb79-b00fe38d9f0c');

                self::assertArrayHasKey('id', $entry);
                self::assertArrayHasKey('title', $entry);

                self::assertSame('28a3e51c-4944-44a7-bb79-b00fe38d9f0c', $entry['id']);

                /** Cached */

                /** @noinspection PhpInternalEntityUsedInspection */
                self::assertTrue(
                    InMemoryStorage::instance()->has('6e1488a47d01310e8697f3e980935b2d4d2c8df3')
                );

                /** @var array $entry */
                $entry = yield $finder->findOneById('28a3e51c-4944-44a7-bb79-b00fe38d9f0c');

                self::assertArrayHasKey('id', $entry);
                self::assertArrayHasKey('title', $entry);

                self::assertSame('28a3e51c-4944-44a7-bb79-b00fe38d9f0c', $entry['id']);
            }
        );
    }

    /**
     * @test
     */
    public function selectAll(): void
    {
        Loop::run(
            static function (): \Generator
            {
                yield self::$adapter->execute(
                    'INSERT INTO qwerty(id, title) VALUES(?,?), (?,?)',
                    [uuid(), 'test1', uuid(), 'test2']
                );

                $finder = new CachedSqlFinder('qwerty', self::$adapter);

                /** @var array $rows */
                $rows = yield $finder->find([], 100, ['id' => 'DESC']);

                self::assertCount(2, $rows);

                /** Cached */

                /** @noinspection PhpInternalEntityUsedInspection */
                self::assertTrue(
                    InMemoryStorage::instance()->has('5c2a559f629ec7eb8cc42876c6fec726b3ab82f2')
                );

                /** @var array $rows */
                $rows = yield $finder->find([], 100, ['id' => 'DESC']);

                self::assertCount(2, $rows);
            }
        );
    }
}
