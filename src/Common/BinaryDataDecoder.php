<?php

/**
 * SQL database adapter implementation.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Common;

/**
 * Unescape binary data.
 */
interface BinaryDataDecoder
{
    /**
     * Unescape binary string.
     *
     * @param resource|string $payload
     */
    public function unescapeBinary($payload): string;
}
