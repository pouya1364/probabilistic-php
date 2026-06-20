<?php

declare(strict_types=1);

namespace Probabilistic\Exception;

use OverflowException;

/**
 * Thrown when a CountingBloomFilter slot would exceed its one-byte maximum,
 * which means the filter is undersized for the workload.
 */
final class CounterOverflowException extends OverflowException implements ExceptionInterface
{
}
