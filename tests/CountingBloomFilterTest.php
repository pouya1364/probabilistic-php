<?php

declare(strict_types=1);

namespace Probabilistic\Tests;

use PHPUnit\Framework\TestCase;
use Probabilistic\CountingBloomFilter;

final class CountingBloomFilterTest extends TestCase
{
    public function testAddThenRemoveReportsAbsent(): void
    {
        $filter = CountingBloomFilter::create(expectedItems: 1_000, falsePositiveRate: 0.01);
        $filter->add('session-abc');
        self::assertTrue($filter->mightContain('session-abc'));

        $filter->remove('session-abc');
        self::assertFalse($filter->mightContain('session-abc'));
    }

    public function testRemovingNeverAddedItemThrows(): void
    {
        $filter = CountingBloomFilter::create(expectedItems: 1_000, falsePositiveRate: 0.01);
        $this->expectException(\LogicException::class);
        $filter->remove('was-never-here');
    }

    public function testRemovingTwiceThrowsOnSecondRemoval(): void
    {
        $filter = CountingBloomFilter::create(expectedItems: 1_000, falsePositiveRate: 0.01);
        $filter->add('once');
        $filter->remove('once');

        $this->expectException(\LogicException::class);
        $filter->remove('once');
    }

    /**
     * Re-adding the same item drives its shared slots toward the cap;
     * the add that would push a counter past 255 must throw rather than
     * silently wrap a single byte back to zero.
     */
    public function testCounterOverflowThrowsAtBoundary(): void
    {
        $filter = CountingBloomFilter::create(expectedItems: 16, falsePositiveRate: 0.01);

        for ($i = 0; $i < 255; $i++) {
            $filter->add('hot-key');
        }

        $this->expectException(\OverflowException::class);
        $filter->add('hot-key');
    }

    public function testNeverReportsFalseNegatives(): void
    {
        $filter = CountingBloomFilter::create(expectedItems: 10_000, falsePositiveRate: 0.01);
        for ($i = 0; $i < 10_000; $i++) {
            $filter->add("item-{$i}");
        }
        for ($i = 0; $i < 10_000; $i++) {
            self::assertTrue($filter->mightContain("item-{$i}"));
        }
    }

    public function testFalsePositiveRateStaysWithinExpectedBounds(): void
    {
        $expectedRate = 0.01;
        $filter = CountingBloomFilter::create(expectedItems: 10_000, falsePositiveRate: $expectedRate);

        for ($i = 0; $i < 10_000; $i++) {
            $filter->add("item-{$i}");
        }

        $falsePositives = 0;
        for ($i = 10_000; $i < 20_000; $i++) {
            if ($filter->mightContain("item-{$i}")) {
                $falsePositives++;
            }
        }

        $observedRate = $falsePositives / 10_000;

        self::assertLessThan(
            $expectedRate * 3,
            $observedRate,
            "False positive rate {$observedRate} is far outside the expected ~{$expectedRate}.",
        );
    }

    public function testRejectsZeroExpectedItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountingBloomFilter::create(expectedItems: 0, falsePositiveRate: 0.01);
    }

    public function testRejectsFalsePositiveRateOutOfRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountingBloomFilter::create(expectedItems: 100, falsePositiveRate: 1.0);
    }
}
