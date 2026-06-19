<?php

declare(strict_types=1);

namespace Probabilistic\Tests;

use PHPUnit\Framework\TestCase;
use Probabilistic\CountMinSketch;

final class CountMinSketchTest extends TestCase
{
    /**
     * With a wide sketch and only a few distinct items, collisions are
     * vanishingly unlikely, so the estimate should match the true count
     * exactly here.
     */
    public function testExactCountsWhenCollisionsUnlikely(): void
    {
        $cms = CountMinSketch::create(width: 10_000, depth: 5);

        $cms->increment('page:/home');
        $cms->increment('page:/home');
        $cms->increment('page:/about');

        self::assertSame(2, $cms->estimate('page:/home'));
        self::assertSame(1, $cms->estimate('page:/about'));
        self::assertSame(0, $cms->estimate('page:/never-seen'));
    }

    public function testIncrementByAmount(): void
    {
        $cms = CountMinSketch::create(width: 10_000, depth: 5);
        $cms->increment('bulk', 50);
        $cms->increment('bulk', 7);
        self::assertSame(57, $cms->estimate('bulk'));
    }

    /**
     * The core guarantee: a count-min sketch never underestimates. Under
     * deliberate collision pressure (a deliberately narrow sketch), some
     * estimates will overshoot — but none may ever fall below the truth.
     */
    public function testNeverUnderestimates(): void
    {
        $cms = CountMinSketch::create(width: 50, depth: 4);

        $truth = [];
        for ($i = 0; $i < 500; $i++) {
            $key = "key-{$i}";
            $times = ($i % 5) + 1;
            for ($t = 0; $t < $times; $t++) {
                $cms->increment($key);
            }
            $truth[$key] = $times;
        }

        foreach ($truth as $key => $trueCount) {
            self::assertGreaterThanOrEqual(
                $trueCount,
                $cms->estimate($key),
                "Underestimate for {$key}: this must never happen.",
            );
        }
    }

    public function testMergeSumsCellsAcrossSketches(): void
    {
        $a = CountMinSketch::create(width: 10_000, depth: 5);
        $b = CountMinSketch::create(width: 10_000, depth: 5);

        $a->increment('shared', 3);
        $a->increment('only-a', 1);
        $b->increment('shared', 4);
        $b->increment('only-b', 9);

        $a->merge($b);

        self::assertSame(7, $a->estimate('shared'));
        self::assertSame(1, $a->estimate('only-a'));
        self::assertSame(9, $a->estimate('only-b'));
    }

    public function testMergeRejectsMismatchedDimensions(): void
    {
        $a = CountMinSketch::create(width: 1_000, depth: 5);
        $b = CountMinSketch::create(width: 2_000, depth: 5);

        $this->expectException(\InvalidArgumentException::class);
        $a->merge($b);
    }

    public function testRejectsZeroWidth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountMinSketch::create(width: 0, depth: 5);
    }

    public function testRejectsZeroDepth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CountMinSketch::create(width: 2_000, depth: 0);
    }
}
