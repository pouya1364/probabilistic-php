<?php

declare(strict_types=1);

namespace Probabilistic;

use Probabilistic\Exception\InvalidConfigurationException;
use Probabilistic\Support\Hasher;

/**
 * Approximate distinct-count (cardinality) estimation using a fraction
 * of the memory an exact count would require.
 *
 * Reference: Flajolet, P., Fusy, É., Gandouet, O., & Meunier, F. (2007).
 * "HyperLogLog: the analysis of a near-optimal cardinality estimation
 * algorithm." Discrete Mathematics and Theoretical Computer Science.
 */
final class HyperLogLog
{
    /** @var int[] register values */
    private array $registers;
    private readonly int $m; // number of registers, always a power of two

    /**
     * @param int $precision number of bits used for the register index.
     *   Higher precision = more accuracy, more memory. 14 is a common
     *   default: 2^14 = 16,384 registers, roughly 16KB of memory,
     *   giving a standard error around 0.81%.
     */
    public function __construct(private readonly int $precision = 14)
    {
        if ($precision < 4 || $precision > 18) {
            throw new InvalidConfigurationException('precision must be between 4 and 18.');
        }
        $this->m = 1 << $precision;
        $this->registers = array_fill(0, $this->m, 0);
    }

    public function add(string $item): void
    {
        // murmur3a, not fnv1a: the estimate is acutely sensitive to hash
        // uniformity, which FNV-1a's weak avalanche does not provide.
        $hash = Hasher::murmur3a($item);

        // Top `precision` bits pick the register; the remaining low bits give
        // the rank. The low bits must be scanned in place — shifting them up
        // to the top of the word would aim leadingZeroCount at the wrong bits
        // and never terminate when the low `precision` bits are zero.
        $registerIndex = $hash >> (32 - $this->precision);
        $remaining = $hash & ((1 << (32 - $this->precision)) - 1);
        $rank = $this->leadingZeroCount($remaining, 32 - $this->precision) + 1;

        $this->registers[$registerIndex] = max($this->registers[$registerIndex], $rank);
    }

    public function estimate(): int
    {
        $alpha = $this->alpha();
        $sumInverse = 0.0;
        $zeroRegisters = 0;

        foreach ($this->registers as $value) {
            $sumInverse += 2 ** (-$value);
            if ($value === 0) {
                $zeroRegisters++;
            }
        }

        $rawEstimate = $alpha * $this->m * $this->m / $sumInverse;

        // Small-range correction: linear counting when many registers
        // are still empty, per the original paper's recommendation.
        if ($rawEstimate <= 2.5 * $this->m && $zeroRegisters > 0) {
            return (int) round($this->m * log($this->m / $zeroRegisters));
        }

        return (int) round($rawEstimate);
    }

    /**
     * Combine two HyperLogLog estimators of identical precision by
     * taking the elementwise maximum of their registers. The merged
     * result is exactly equivalent to a single HLL that had observed
     * the union of both input streams.
     */
    public function merge(self $other): void
    {
        if ($other->precision !== $this->precision) {
            throw new InvalidConfigurationException('Cannot merge HyperLogLog instances with different precision.');
        }
        for ($i = 0; $i < $this->m; $i++) {
            $this->registers[$i] = max($this->registers[$i], $other->registers[$i]);
        }
    }

    /**
     * Bias-correction constant. Small register counts (16, 32, 64) use
     * the special-cased values from the original paper rather than the
     * general asymptotic formula, which is only accurate for m >= 128.
     */
    private function alpha(): float
    {
        return match (true) {
            $this->m === 16 => 0.673,
            $this->m === 32 => 0.697,
            $this->m === 64 => 0.709,
            default => 0.7213 / (1 + 1.079 / $this->m),
        };
    }

    /**
     * Number of leading zero bits in the low `bitWidth` bits of $value,
     * scanning from the most significant of those bits. Returns $bitWidth
     * when none of them are set.
     */
    private function leadingZeroCount(int $value, int $bitWidth): int
    {
        for ($count = 0; $count < $bitWidth; $count++) {
            if (($value & (1 << ($bitWidth - 1 - $count))) !== 0) {
                return $count;
            }
        }
        return $bitWidth;
    }
}
