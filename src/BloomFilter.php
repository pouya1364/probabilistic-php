<?php

declare(strict_types=1);

namespace Probabilistic;

use Probabilistic\Exception\InvalidConfigurationException;
use Probabilistic\Support\BitArray;
use Probabilistic\Support\Hasher;

/**
 * Space-efficient probabilistic set membership testing.
 *
 * Guarantees zero false negatives: if mightContain() returns false,
 * the item was definitely never added. If it returns true, the item
 * was probably added — false positives occur at the configured rate.
 *
 * Reference: Bloom, B. H. (1970). "Space/time trade-offs in hash coding
 * with allowable errors." Communications of the ACM, 13(7), 422-426.
 */
final readonly class BloomFilter
{
    private function __construct(
        private BitArray $bits,
        private int $hashCount,
    ) {
    }

    /**
     * @param int $expectedItems how many items you plan to add
     * @param float $falsePositiveRate desired false positive rate, e.g. 0.01 for 1%
     */
    public static function create(int $expectedItems, float $falsePositiveRate): self
    {
        if ($expectedItems < 1) {
            throw new InvalidConfigurationException('expectedItems must be at least 1.');
        }
        if ($falsePositiveRate <= 0 || $falsePositiveRate >= 1) {
            throw new InvalidConfigurationException('falsePositiveRate must be between 0 and 1, exclusive.');
        }

        $size = self::optimalBitArraySize($expectedItems, $falsePositiveRate);
        $hashCount = self::optimalHashCount($size, $expectedItems);

        return new self(new BitArray($size), $hashCount);
    }

    public function add(string $item): void
    {
        foreach ($this->hashIndices($item) as $index) {
            $this->bits->set($index);
        }
    }

    public function mightContain(string $item): bool
    {
        foreach ($this->hashIndices($item) as $index) {
            if (!$this->bits->get($index)) {
                return false;
            }
        }
        return true;
    }

    /**
     * m = ceil( -(n * ln(p)) / (ln(2))^2 )
     * The standard optimal bit array size formula.
     */
    private static function optimalBitArraySize(int $n, float $p): int
    {
        $m = -($n * log($p)) / (log(2) ** 2);
        return (int) ceil($m);
    }

    /**
     * k = round( (m / n) * ln(2) )
     * The standard optimal hash function count formula.
     */
    private static function optimalHashCount(int $m, int $n): int
    {
        $k = ($m / $n) * log(2);
        return max(1, (int) round($k));
    }

    /**
     * @return int[]
     */
    private function hashIndices(string $item): array
    {
        return Hasher::deriveHashes($item, $this->hashCount, $this->bits->size());
    }
}
