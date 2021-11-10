<?php

/**
 * SQL database adapter implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types=0);

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
