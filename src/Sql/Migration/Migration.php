<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Storage\Sql\Migration;

/**
 *
 */
abstract class Migration
{
    /** @var array */
    private $queries = [];

    /**
     * @psaln-var array<string, array>
     *
     * @var array
     */
    private $params = [];

    final public function __construct()
    {
    }

    final protected function add(string $query, array $params = []): void
    {
        if ($query !== '')
        {
            $this->queries[]             = $query;
            $this->params[\sha1($query)] = $params;
        }
    }

    abstract protected function up(): void;

    abstract protected function down(): void;
}
