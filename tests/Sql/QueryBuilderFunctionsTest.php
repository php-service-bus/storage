<?php /** @noinspection PhpUnhandledExceptionInspection */

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Tests\Sql;

use function ServiceBus\Storage\Sql\buildQuery;
use function ServiceBus\Storage\Sql\cast;
use function ServiceBus\Storage\Sql\deleteQuery;
use function ServiceBus\Storage\Sql\equalsCriteria;
use function ServiceBus\Storage\Sql\insertQuery;
use function ServiceBus\Storage\Sql\notEqualsCriteria;
use function ServiceBus\Storage\Sql\selectQuery;
use function ServiceBus\Storage\Sql\toSnakeCase;
use function ServiceBus\Storage\Sql\updateQuery;
use PHPUnit\Framework\TestCase;

/**
 *
 */
final class QueryBuilderFunctionsTest extends TestCase
{
    /**
     * @test
     */
    public function buildQuery(): void
    {
        $parts = buildQuery(
            selectQuery('table_name'),
            [equalsCriteria('id', 100), notEqualsCriteria('id', 200)],
            ['title' => 'desc'],
            100
        );

        self::assertCount(2, $parts);
        self::assertArrayHasKey(0, $parts);
        self::assertArrayHasKey(1, $parts);

        self::assertSame(
            'SELECT * FROM "table_name" WHERE "id" = ? AND "id" != ? ORDER BY "title" DESC LIMIT 100',
            $parts[0]
        );

        self::assertSame([100, 200], $parts[1]);
    }

    /**
     * @test
     */
    public function selectQuery(): void
    {
        $query = selectQuery('test', 'id', 'value')
            ->where(equalsCriteria('id', '100500'))
            ->compile();

        self::assertSame(
            'SELECT "id", "value" FROM "test" WHERE "id" = ?',
            $query->sql()
        );

        self::assertSame(['100500'], $query->params());
    }

    /**
     * @test
     */
    public function updateQuery(): void
    {
        $query = updateQuery('test', ['name' => 'newName', 'email' => 'newEmail'])
            ->where(equalsCriteria('id', '100500'))
            ->compile();

        self::assertSame(
            'UPDATE "test" SET "name" = ?, "email" = ? WHERE "id" = ?',
            $query->sql()
        );

        self::assertSame(['newName', 'newEmail', '100500'], $query->params());
    }

    /**
     * @test
     */
    public function deleteQuery(): void
    {
        $query = deleteQuery('test')->compile();

        self::assertSame('DELETE FROM "test"', $query->sql());
        self::assertEmpty($query->params());
    }

    /**
     * @test
     */
    public function insertQueryFromObject(): void
    {
        $object = new class('qwerty', 'root')
        {
            private $first;

            private $second;

            /**
             * @param $first
             * @param $second
             */
            public function __construct($first, $second)
            {
                /** @noinspection UnusedConstructorDependenciesInspection */
                $this->first = $first;
                /** @noinspection UnusedConstructorDependenciesInspection */
                $this->second = $second;
            }
        };

        $query = insertQuery('test', $object)->compile();

        self::assertSame(
            'INSERT INTO "test" ("first", "second") VALUES (?, ?)',
            $query->sql()
        );

        self::assertSame(['qwerty', 'root'], $query->params());
    }

    /**
     * @test
     */
    public function insertQueryFromArray(): void
    {
        $query = insertQuery('test', ['first' => 'qwerty', 'second' => 'root'])->compile();

        self::assertSame(
            'INSERT INTO "test" ("first", "second") VALUES (?, ?)',
            $query->sql()
        );

        self::assertSame(['qwerty', 'root'], $query->params());
    }

    /**
     * @test
     */
    public function toSnakeCase(): void
    {
        self::assertSame(
            'some_snake_case',
            toSnakeCase('someSnakeCase')
        );
    }

    /**
     * @test
     */
    public function castObjectWithoutToString(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('"Closure" must implements "__toString" method');

        cast(
            static function (): void
            {
            }
        );
    }

    /**
     * @test
     */
    public function castObjectWithToString(): void
    {
        $object = new class()
        {
            public function __toString()
            {
                return 'qwerty';
            }
        };

        self::assertSame('qwerty', cast($object));
    }

    /**
     * @test
     */
    public function objectNotEqualsCriteria(): void
    {
        $object = new class()
        {
            /** @var string */
            private $id;

            public function __construct()
            {
                $this->id = 'uuid';
            }

            public function __toString()
            {
                return $this->id;
            }
        };

        $query = selectQuery('test')->where(notEqualsCriteria('id', $object))->compile();

        self::assertSame('SELECT * FROM "test" WHERE "id" != ?', $query->sql());
        self::assertSame([(string) $object], $query->params());
    }

    /**
     * @test
     */
    public function scalarNotEqualsCriteria(): void
    {
        $id = 'uuid';

        $query = selectQuery('test')->where(notEqualsCriteria('id', $id))->compile();

        self::assertSame('SELECT * FROM "test" WHERE "id" != ?', $query->sql());
        self::assertSame([$id], $query->params());
    }
}
