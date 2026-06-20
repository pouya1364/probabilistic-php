<?php

declare(strict_types=1);

namespace Probabilistic\Tests;

use PHPUnit\Framework\TestCase;
use Probabilistic\BloomFilter;
use Probabilistic\Exception\InvalidConfigurationException;

final class BloomFilterTest extends TestCase
{
    /**
     * The defining guarantee: an item that was added must always report
     * present. A single false negative anywhere is a hard failure, so we
     * exercise a large batch rather than a token few.
     */
    public function testNeverReportsFalseNegatives(): void
    {
        $filter = BloomFilter::create(expectedItems: 10_000, falsePositiveRate: 0.01);

        for ($i = 0; $i < 10_000; $i++) {
            $filter->add("item-$i");
        }

        for ($i = 0; $i < 10_000; $i++) {
            self::assertTrue(
                $filter->mightContain("item-$i"),
                "False negative for item-$i; this must never happen.",
            );
        }
    }

    public function testFalsePositiveRateStaysWithinExpectedBounds(): void
    {
        $expectedRate = 0.01;
        $filter = BloomFilter::create(expectedItems: 10_000, falsePositiveRate: $expectedRate);

        for ($i = 0; $i < 10_000; $i++) {
            $filter->add("item-$i");
        }

        $falsePositives = 0;
        for ($i = 10_000; $i < 20_000; $i++) {
            if ($filter->mightContain("item-$i")) {
                $falsePositives++;
            }
        }

        $observedRate = $falsePositives / 10_000;

        // The configured rate is a target, not a hard ceiling for one trial.
        // 3x leaves room for natural variance while still catching real bugs.
        self::assertLessThan(
            $expectedRate * 3,
            $observedRate,
            "False positive rate $observedRate is far outside the expected ~$expectedRate.",
        );
    }

    public function testFreshFilterContainsNothing(): void
    {
        $filter = BloomFilter::create(expectedItems: 100, falsePositiveRate: 0.01);
        self::assertFalse($filter->mightContain('never-added'));
    }

    public function testRejectsZeroExpectedItems(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        BloomFilter::create(expectedItems: 0, falsePositiveRate: 0.01);
    }

    public function testRejectsNegativeExpectedItems(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        BloomFilter::create(expectedItems: -1, falsePositiveRate: 0.01);
    }

    public function testRejectsFalsePositiveRateOfZero(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        BloomFilter::create(expectedItems: 100, falsePositiveRate: 0.0);
    }

    public function testRejectsFalsePositiveRateOfOne(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        BloomFilter::create(expectedItems: 100, falsePositiveRate: 1.0);
    }

    public function testRejectsNegativeFalsePositiveRate(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        BloomFilter::create(expectedItems: 100, falsePositiveRate: -0.1);
    }
}
