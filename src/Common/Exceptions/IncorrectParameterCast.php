<?php

/**
 * Common storage parts.
 *
 * @author  Maksim Masiukevich <dev@async-php.com>
 * @license MIT
 * @license https://opensource.org/licenses/MIT
 */

declare(strict_types = 1);

namespace ServiceBus\Storage\Common\Exceptions;

/**
 *
 */
final class IncorrectParameterCast extends \LogicException implements StorageExceptionMarker
{
}
