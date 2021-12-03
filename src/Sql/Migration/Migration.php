<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

namespace ServiceBus\Storage\Sql\Migration;

abstract class Migration
{
    /**
     * @psalm-var array<array-key, non-empty-string>
     *
     * @var array
     */
    private $queries = [];

    /**
     * @psalm-var array<non-empty-string, array<array-key, string|int|float|null>>
     *
     * @var array
     */
    private $params = [];

    /**
     * @psalm-param string                                  $query
     * @psalm-param array<array-key, string|int|float|null> $params
     */
    final protected function add(string $query, array $params = []): void
    {
        if ($query !== '')
        {
            /** @psalm-var non-empty-string $queryKey */
            $queryKey = \sha1($query);

            $this->queries[]             = $query;
            $this->params[$queryKey] = $params;
        }
    }

    abstract protected function up(): void;

    abstract protected function down(): void;

    /**
     * Close constructor
     *
     * @codeCoverageIgnore
     */
    private function __construct()
    {
    }
}
