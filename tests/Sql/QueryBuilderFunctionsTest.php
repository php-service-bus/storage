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
     *
     * @throws \Throwable
     */
    public function buildQuery(): void
    {
        $parts = buildQuery(
            selectQuery('table_name'),
            [equalsCriteria('id', 100), notEqualsCriteria('id', 200)],
            ['title' => 'desc'],
            100
        );

        static::assertCount(2, $parts);
        static::assertArrayHasKey(0, $parts);
        static::assertArrayHasKey(1, $parts);

        static::assertSame(
            'SELECT * FROM "table_name" WHERE "id" = ? AND "id" != ? ORDER BY "title" DESC LIMIT 100',
            $parts[0]
        );

        static::assertSame([100, 200], $parts[1]);
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function selectQuery(): void
    {
        $query = selectQuery('test', 'id', 'value')
            ->where(equalsCriteria('id', '100500'))
            ->compile();

        static::assertSame(
            'SELECT "id", "value" FROM "test" WHERE "id" = ?',
            $query->sql()
        );

        static::assertSame(['100500'], $query->params());
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function updateQuery(): void
    {
        $query = updateQuery('test', ['name' => 'newName', 'email' => 'newEmail'])
            ->where(equalsCriteria('id', '100500'))
            ->compile();

        static::assertSame(
            'UPDATE "test" SET "name" = ?, "email" = ? WHERE "id" = ?',
            $query->sql()
        );

        static::assertSame(['newName', 'newEmail', '100500'], $query->params());
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function deleteQuery(): void
    {
        $query = deleteQuery('test')->compile();

        static::assertSame('DELETE FROM "test"', $query->sql());
        static::assertEmpty($query->params());
    }

    /**
     * @test
     *
     * @throws \Throwable
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

        static::assertSame(
            'INSERT INTO "test" ("first", "second") VALUES (?, ?)',
            $query->sql()
        );

        static::assertSame(['qwerty', 'root'], $query->params());
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function insertQueryFromArray(): void
    {
        $query = insertQuery('test', ['first' => 'qwerty', 'second' => 'root'])->compile();

        static::assertSame(
            'INSERT INTO "test" ("first", "second") VALUES (?, ?)',
            $query->sql()
        );

        static::assertSame(['qwerty', 'root'], $query->params());
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function toSnakeCase(): void
    {
        static::assertSame(
            'some_snake_case',
            toSnakeCase('someSnakeCase')
        );
    }

    /**
     * @test
     *
     * @throws \Throwable
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
     *
     * @throws \Throwable
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

        static::assertSame('qwerty', cast($object));
    }

    /**
     * @test
     *
     * @throws \Throwable
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

        static::assertSame('SELECT * FROM "test" WHERE "id" != ?', $query->sql());
        static::assertSame([(string) $object], $query->params());
    }

    /**
     * @test
     *
     * @throws \Throwable
     */
    public function scalarNotEqualsCriteria(): void
    {
        $id = 'uuid';

        $query = selectQuery('test')->where(notEqualsCriteria('id', $id))->compile();

        static::assertSame('SELECT * FROM "test" WHERE "id" != ?', $query->sql());
        static::assertSame([$id], $query->params());
    }
}
