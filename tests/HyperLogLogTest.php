<?php

declare(strict_types=1);

namespace Probabilistic\Tests;

use PHPUnit\Framework\TestCase;
use Probabilistic\Exception\InvalidConfigurationException;
use Probabilistic\HyperLogLog;

final class HyperLogLogTest extends TestCase
{
    /**
     * 100k distinct sits well above the small-range correction threshold
     * (2.5 * 16384 ~= 41k), so this genuinely exercises the main estimator
     * rather than linear counting. At precision 14 the standard error is
     * under 1%, so a 10% tolerance never flakes yet still catches a broken
     * estimator.
     */
    public function testEstimateAccuracyForLargeCardinality(): void
    {
        $hll = new HyperLogLog(14);
        $distinct = 100_000;
        for ($i = 0; $i < $distinct; $i++) {
            $hll->add("visitor-{$i}");
        }

        $estimate = $hll->estimate();
        $error = abs($estimate - $distinct) / $distinct;

        self::assertLessThan(
            0.10,
            $error,
            "Estimate {$estimate} is more than 10% off the true {$distinct}.",
        );
    }

    /**
     * Adding one distinct value many times is still a cardinality of one;
     * the small-range linear-counting correction should land right at ~1.
     */
    public function testSmallCardinalityWithRepeatedItem(): void
    {
        $hll = new HyperLogLog(14);
        for ($i = 0; $i < 5_000; $i++) {
            $hll->add('same-value');
        }

        self::assertGreaterThanOrEqual(1, $hll->estimate());
        self::assertLessThanOrEqual(3, $hll->estimate());
    }

    public function testEmptyEstimateIsZero(): void
    {
        self::assertSame(0, (new HyperLogLog(14))->estimate());
    }

    /**
     * Merging is the elementwise max of registers, which is by definition
     * identical to the registers a single estimator would hold after
     * observing the union — so the merged estimate must match exactly,
     * not merely approximately.
     */
    public function testMergeEquivalentToSingleEstimatorOverUnion(): void
    {
        $a = new HyperLogLog(14);
        $b = new HyperLogLog(14);
        $union = new HyperLogLog(14);

        for ($i = 0; $i < 30_000; $i++) {
            $a->add("a-{$i}");
            $union->add("a-{$i}");
        }
        for ($i = 0; $i < 30_000; $i++) {
            $b->add("b-{$i}");
            $union->add("b-{$i}");
        }

        $a->merge($b);

        self::assertSame($union->estimate(), $a->estimate());
    }

    public function testMergeRejectsDifferentPrecision(): void
    {
        $a = new HyperLogLog(14);
        $b = new HyperLogLog(12);

        $this->expectException(InvalidConfigurationException::class);
        $a->merge($b);
    }

    public function testRejectsPrecisionBelowMinimum(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new HyperLogLog(3);
    }

    public function testRejectsPrecisionAboveMaximum(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        new HyperLogLog(19);
    }
}
