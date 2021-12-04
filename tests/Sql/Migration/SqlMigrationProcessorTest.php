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

namespace ServiceBus\Storage\Tests\Sql\Migration;

use PHPUnit\Framework\TestCase;
use ServiceBus\Storage\Common\DatabaseAdapter;
use ServiceBus\Storage\Sql\Migration\SqlMigrationLoader;
use ServiceBus\Storage\Sql\Migration\SqlMigrationProcessor;
use function Amp\Promise\wait;
use function ServiceBus\Storage\Sql\DoctrineDBAL\inMemoryAdapter;

/**
 *
 */
final class SqlMigrationProcessorTest extends TestCase
{
    /**
     * @var DatabaseAdapter
     */
    private $storage;

    /**
     * @var SqlMigrationProcessor
     */
    private $migrationProcessor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storage            = inMemoryAdapter();
        $this->migrationProcessor = new SqlMigrationProcessor(
            $this->storage,
            new SqlMigrationLoader(__DIR__ . '/stubs')
        );
    }

    /**
     * @test
     */
    public function up(): void
    {
        self::assertSame(2, wait($this->migrationProcessor->up()));
    }

    /**
     * @test
     */
    public function down(): void
    {
        self::assertSame(1, wait($this->migrationProcessor->down()));
    }
}
