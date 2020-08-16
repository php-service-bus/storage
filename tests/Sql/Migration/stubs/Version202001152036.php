<?php

/**
 * SQL databases adapters implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

use ServiceBus\Storage\Sql\Migration\Migration;

/**
 *
 */
final class Version202001152036 extends Migration
{
    protected function up(): void
    {
        $this->add('SELECT datetime(\'now\',\'localtime\');');
    }

    protected function down(): void
    {
    }
}
