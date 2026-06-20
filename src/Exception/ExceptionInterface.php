<?php

declare(strict_types=1);

namespace Probabilistic\Exception;

use Throwable;

/**
 * Implemented by every exception this library throws, so a caller can catch
 * everything from the package with a single catch (ExceptionInterface).
 */
interface ExceptionInterface extends Throwable
{
}
