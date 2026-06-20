<?php

declare(strict_types=1);

namespace Probabilistic\Tests;

use PHPUnit\Framework\TestCase;
use Probabilistic\CuckooFilter;
use Probabilistic\Exception\FilterFullException;
use Probabilistic\Exception\InvalidConfigurationException;

final class CuckooFilterTest extends TestCase
{
    public function testAddThenContains(): void
    {
        $filter = CuckooFilter::create(expectedItems: 1_000);
        $filter->add('192.168.1.1');
        self::assertTrue($filter->contains('192.168.1.1'));
    }

    public function testAddThenRemoveThenContains(): void
    {
        $filter = CuckooFilter::create(expectedItems: 1_000);
        $filter->add('192.168.1.1');
        self::assertTrue($filter->remove('192.168.1.1'));
        self::assertFalse($filter->contains('192.168.1.1'));
    }

    public function testRemovingAbsentItemReturnsFalse(): void
    {
        $filter = CuckooFilter::create(expectedItems: 1_000);
        self::assertFalse($filter->remove('never-added'));
    }

    /**
     * Items relocated by eviction must still be findable. At ~60% load a
     * large batch will trigger real evictions, so the broken XOR involution
     * would surface here as a false negative.
     */
    public function testNeverReportsFalseNegativesEvenAfterEvictions(): void
    {
        $filter = CuckooFilter::create(expectedItems: 10_000);
        for ($i = 0; $i < 10_000; $i++) {
            $filter->add("item-$i");
        }
        for ($i = 0; $i < 10_000; $i++) {
            self::assertTrue(
                $filter->contains("item-$i"),
                "False negative for item-$i after eviction; the XOR trick is inconsistent.",
            );
        }
    }

    public function testFalsePositiveRateStaysWithinExpectedBounds(): void
    {
        $filter = CuckooFilter::create(expectedItems: 10_000);
        for ($i = 0; $i < 10_000; $i++) {
            $filter->add("item-$i");
        }

        $falsePositives = 0;
        for ($i = 10_000; $i < 20_000; $i++) {
            if ($filter->contains("item-$i")) {
                $falsePositives++;
            }
        }

        $observedRate = $falsePositives / 10_000;

        // With 8-bit fingerprints and 4-slot buckets, the theoretical ceiling
        // is roughly 2*4/2^8 ~= 3%. 5% leaves room for variance while still
        // catching a fingerprinting or bucket-indexing bug.
        self::assertLessThan(
            0.05,
            $observedRate,
            "False positive rate $observedRate is far above the expected ~3%.",
        );
    }

    /**
     * A filter sized for a single item has only a handful of slots. Pushing
     * far more distinct items into it must fail loudly with FilterFullException
     * after the bounded eviction loop — never hang, never silently drop data.
     */
    public function testOverflowThrowsFilterFullException(): void
    {
        $filter = CuckooFilter::create(expectedItems: 1);

        $this->expectException(FilterFullException::class);
        for ($i = 0; $i < 1_000; $i++) {
            $filter->add("overflow-$i");
        }
    }

    public function testRejectsZeroExpectedItems(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        CuckooFilter::create(expectedItems: 0);
    }
}
