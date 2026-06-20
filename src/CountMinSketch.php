<?php

declare(strict_types=1);

namespace Probabilistic;

use Probabilistic\Exception\InvalidConfigurationException;
use Probabilistic\Support\Hasher;

/**
 * Approximate frequency counting for high-volume event streams.
 *
 * Counts are never underestimated — only ever equal to or higher than
 * the true count, due to hash collisions adding to other items' counts.
 * Larger width/depth reduces this overestimation at the cost of memory.
 *
 * Reference: Cormode, G., & Muthukrishnan, S. (2005). "An improved data
 * stream summary: the count-min sketch and its applications."
 * Journal of Algorithms, 55(1), 58-75.
 */
final class CountMinSketch
{
    /** @var int[][] depth rows x width columns */
    private array $table;

    private function __construct(
        private readonly int $width,
        private readonly int $depth,
    ) {
        $this->table = array_fill(0, $depth, array_fill(0, $width, 0));
    }

    public static function create(int $width, int $depth): self
    {
        if ($width < 1 || $depth < 1) {
            throw new InvalidConfigurationException('width and depth must each be at least 1.');
        }
        return new self($width, $depth);
    }

    public function increment(string $item, int $amount = 1): void
    {
        foreach ($this->rowIndices($item) as $row => $col) {
            $this->table[$row][$col] += $amount;
        }
    }

    /**
     * The estimate is the minimum across all rows — the row least
     * affected by hash collisions for this particular item.
     */
    public function estimate(string $item): int
    {
        $min = PHP_INT_MAX;
        foreach ($this->rowIndices($item) as $row => $col) {
            $min = min($min, $this->table[$row][$col]);
        }
        return $min;
    }

    /**
     * Combine two sketches of identical dimensions by summing each cell.
     * Useful for merging counts collected from parallel/distributed
     * workers without re-processing the raw events.
     */
    public function merge(self $other): void
    {
        if ($other->width !== $this->width || $other->depth !== $this->depth) {
            throw new InvalidConfigurationException('Cannot merge sketches with different dimensions.');
        }
        for ($row = 0; $row < $this->depth; $row++) {
            for ($col = 0; $col < $this->width; $col++) {
                $this->table[$row][$col] += $other->table[$row][$col];
            }
        }
    }

    /**
     * One column index per row (depth total). deriveHashes already returns a
     * 0-indexed list, so its keys are the row numbers directly.
     *
     * @return int[] row => column index
     */
    private function rowIndices(string $item): array
    {
        return Hasher::deriveHashes($item, $this->depth, $this->width);
    }
}
