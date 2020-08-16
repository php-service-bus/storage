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

final class Version201912201932 extends Migration
{
    protected function up(): void
    {
        $this->add('SELECT NOW()');
    }

    protected function down(): void
    {
        $this->add('SELECT NOW()');
    }
}
