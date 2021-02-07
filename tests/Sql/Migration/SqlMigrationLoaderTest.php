<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Tests\Sql\Migration;

use PHPUnit\Framework\TestCase;
use ServiceBus\Storage\Sql\Migration\SqlMigrationLoader;
use function Amp\Promise\wait;

/**
 *
 */
final class SqlMigrationLoaderTest extends TestCase
{
    /**
     * @test
     */
    public function load(): void
    {
        $loader = new SqlMigrationLoader(__DIR__ . '/stubs');

        self::assertCount(2, wait($loader->load()));
    }
}
