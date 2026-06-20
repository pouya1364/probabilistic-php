<?php

declare(strict_types=1);

namespace Probabilistic\Exception;

use InvalidArgumentException;

/**
 * Thrown when a structure is created with invalid parameters, or when an
 * operation receives an incompatible argument (such as merging two structures
 * of different dimensions).
 */
final class InvalidConfigurationException extends InvalidArgumentException implements ExceptionInterface
{
}
