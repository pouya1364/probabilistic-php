<?php

declare(strict_types=1);

namespace Probabilistic\Support;

use Probabilistic\Exception\IndexOutOfRangeException;
use Probabilistic\Exception\InvalidConfigurationException;

/**
 * A fixed-size, memory-efficient array of bits backed by a PHP binary string.
 * Each byte of the underlying string stores 8 bits, making this roughly
 * 8x more memory-efficient than using a PHP array of booleans.
 */
final class BitArray
{
    private string $bits;
    private readonly int $size;

    public function __construct(int $size)
    {
        if ($size < 1) {
            throw new InvalidConfigurationException('BitArray size must be at least 1.');
        }
        $this->size = $size;
        $byteLength = (int) ceil($size / 8);
        $this->bits = str_repeat("\0", $byteLength);
    }

    public function set(int $index): void
    {
        $this->assertInBounds($index);
        $byteIndex = $index >> 3;
        $bitOffset = $index & 7;
        $byte = ord($this->bits[$byteIndex]);
        $this->bits[$byteIndex] = chr($byte | (1 << $bitOffset));
    }

    public function get(int $index): bool
    {
        $this->assertInBounds($index);
        $byteIndex = $index >> 3;
        $bitOffset = $index & 7;
        $byte = ord($this->bits[$byteIndex]);
        return ($byte & (1 << $bitOffset)) !== 0;
    }

    public function size(): int
    {
        return $this->size;
    }

    /**
     * Count of bits currently set to 1 — useful for diagnostics
     * and estimating a Bloom filter's current saturation.
     */
    public function countSetBits(): int
    {
        $count = 0;
        for ($i = 0; $i < $this->size; $i++) {
            if ($this->get($i)) {
                $count++;
            }
        }
        return $count;
    }

    private function assertInBounds(int $index): void
    {
        if ($index < 0 || $index >= $this->size) {
            throw new IndexOutOfRangeException(
                "Bit index $index is out of bounds for a BitArray of size $this->size."
            );
        }
    }
}
