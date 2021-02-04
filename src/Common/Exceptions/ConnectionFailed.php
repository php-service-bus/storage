<?php

/**
 * SQL database adapter implementation.
 *
 * @author  Maksim Masiukevich <contacts@desperado.dev>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 0);

namespace ServiceBus\Storage\Common\Exceptions;

/**
 *
 */
final class ConnectionFailed extends \RuntimeException implements StorageExceptionMarker
{
}
