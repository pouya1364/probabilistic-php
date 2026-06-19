<?php

declare(strict_types=1);

namespace Probabilistic\Tests\Support;

use PHPUnit\Framework\TestCase;
use Probabilistic\Support\BitArray;

final class BitArrayTest extends TestCase
{
    public function testNewBitArrayIsAllZero(): void
    {
        $bits = new BitArray(64);
        self::assertSame(0, $bits->countSetBits());
        for ($i = 0; $i < 64; $i++) {
            self::assertFalse($bits->get($i));
        }
    }

    public function testSetAndGet(): void
    {
        $bits = new BitArray(16);
        $bits->set(0);
        $bits->set(7);
        $bits->set(8);
        $bits->set(15);

        self::assertTrue($bits->get(0));
        self::assertTrue($bits->get(7));
        self::assertTrue($bits->get(8));
        self::assertTrue($bits->get(15));
        self::assertFalse($bits->get(1));
        self::assertFalse($bits->get(9));
    }

    public function testSetIsIdempotent(): void
    {
        $bits = new BitArray(8);
        $bits->set(3);
        $bits->set(3);
        self::assertTrue($bits->get(3));
        self::assertSame(1, $bits->countSetBits());
    }

    public function testCountSetBits(): void
    {
        $bits = new BitArray(100);
        foreach ([0, 33, 66, 99] as $index) {
            $bits->set($index);
        }
        self::assertSame(4, $bits->countSetBits());
    }

    public function testSizeReportsConfiguredSize(): void
    {
        self::assertSame(1, (new BitArray(1))->size());
        self::assertSame(12345, (new BitArray(12345))->size());
    }

    /**
     * Size 9 spans two bytes but only exposes indices 0-8; the spare
     * bits in the second byte must never be reachable.
     */
    public function testSizeNotAlignedToByteBoundary(): void
    {
        $bits = new BitArray(9);
        $bits->set(8);
        self::assertTrue($bits->get(8));
        $this->expectException(\OutOfRangeException::class);
        $bits->get(9);
    }

    public function testConstructorRejectsZeroSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BitArray(0);
    }

    public function testConstructorRejectsNegativeSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new BitArray(-5);
    }

    public function testGetOutOfBoundsThrows(): void
    {
        $this->expectException(\OutOfRangeException::class);
        (new BitArray(10))->get(10);
    }

    public function testSetOutOfBoundsThrows(): void
    {
        $this->expectException(\OutOfRangeException::class);
        (new BitArray(10))->set(10);
    }

    public function testNegativeIndexThrows(): void
    {
        $this->expectException(\OutOfRangeException::class);
        (new BitArray(10))->get(-1);
    }
}
