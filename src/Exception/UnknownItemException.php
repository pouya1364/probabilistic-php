<?php

declare(strict_types=1);

namespace Probabilistic\Exception;

use LogicException;

/**
 * Thrown when removing an item that was never added (or was already removed),
 * which would corrupt the counters of other items sharing its slots.
 */
final class UnknownItemException extends LogicException implements ExceptionInterface
{
}
