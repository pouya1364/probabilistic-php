<?php

declare(strict_types=1);

namespace Probabilistic\Exception;

use OutOfRangeException;

/**
 * Thrown when a bit index falls outside the bounds of a BitArray.
 */
final class IndexOutOfRangeException extends OutOfRangeException implements ExceptionInterface
{
}
