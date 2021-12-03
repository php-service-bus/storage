<?php

declare(strict_types=1);

namespace ServiceBus\Storage\Sql\Migration;

enum MigrationType
{
    case UP;
    case DOWN;
}
