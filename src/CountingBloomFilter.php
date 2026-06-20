<?php

declare(strict_types=1);

namespace Probabilistic;

use Probabilistic\Exception\CounterOverflowException;
use Probabilistic\Exception\InvalidConfigurationException;
use Probabilistic\Exception\UnknownItemException;
use Probabilistic\Support\Hasher;

/**
 * Like BloomFilter, but supports removal of items via per-slot counters
 * instead of single bits. Each slot is capped at 255 to keep memory
 * compact (1 byte per slot); incrementing past 255 throws, since that
 * indicates either a severely undersized filter or pathological reuse
 * of the same item far beyond what was planned for.
 */
final class CountingBloomFilter
{
    private const MAX_COUNT = 255;

    /** @var int[] */
    private array $counters;

    private function __construct(
        private readonly int $size,
        private readonly int $hashCount,
    ) {
        $this->counters = array_fill(0, $size, 0);
    }

    public static function create(int $expectedItems, float $falsePositiveRate): self
    {
        if ($expectedItems < 1) {
            throw new InvalidConfigurationException('expectedItems must be at least 1.');
        }
        if ($falsePositiveRate <= 0 || $falsePositiveRate >= 1) {
            throw new InvalidConfigurationException('falsePositiveRate must be between 0 and 1, exclusive.');
        }

        $size = (int) ceil(-($expectedItems * log($falsePositiveRate)) / (log(2) ** 2));
        $hashCount = max(1, (int) round(($size / $expectedItems) * log(2)));

        return new self($size, $hashCount);
    }

    public function add(string $item): void
    {
        foreach ($this->hashIndices($item) as $index) {
            if ($this->counters[$index] >= self::MAX_COUNT) {
                throw new CounterOverflowException(
                    "Counter at index $index reached the maximum of " . self::MAX_COUNT .
                    '. The filter is undersized for this workload.'
                );
            }
            $this->counters[$index]++;
        }
    }

    /**
     * Removing an item that was never added is a logic error in the
     * caller's code (it would corrupt counters for other items sharing
     * that slot), so this throws rather than silently doing nothing.
     */
    public function remove(string $item): void
    {
        if (!$this->mightContain($item)) {
            throw new UnknownItemException('Cannot remove an item that was never added (or already removed).');
        }
        foreach ($this->hashIndices($item) as $index) {
            $this->counters[$index]--;
        }
    }

    public function mightContain(string $item): bool
    {
        foreach ($this->hashIndices($item) as $index) {
            if ($this->counters[$index] === 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return int[]
     */
    private function hashIndices(string $item): array
    {
        return Hasher::deriveHashes($item, $this->hashCount, $this->size);
    }
}
