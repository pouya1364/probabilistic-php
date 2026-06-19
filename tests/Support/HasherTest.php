<?php

declare(strict_types=1);

namespace Probabilistic\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Probabilistic\Support\Hasher;

final class HasherTest extends TestCase
{
    /**
     * Reference vectors from the official FNV-1a 32-bit specification.
     * If these drift, the masking/overflow handling is broken and every
     * structure built on top of the hasher is silently corrupted.
     *
     * @return array<string, array{string, int}>
     */
    public static function fnv1aReferenceVectors(): array
    {
        return [
            'empty string' => ['', 0x811c9dc5],
            'single a' => ['a', 0xe40c292c],
            'foobar' => ['foobar', 0xbf9cf968],
        ];
    }

    #[DataProvider('fnv1aReferenceVectors')]
    public function testFnv1aMatchesReferenceVectors(string $input, int $expected): void
    {
        self::assertSame($expected, Hasher::fnv1a($input));
    }

    public function testFnv1aStaysWithin32BitUnsignedRange(): void
    {
        foreach (['', 'a', 'the quick brown fox', str_repeat('x', 1000)] as $input) {
            $hash = Hasher::fnv1a($input);
            self::assertGreaterThanOrEqual(0, $hash);
            self::assertLessThanOrEqual(0xFFFFFFFF, $hash);
        }
    }

    public function testDeriveHashesReturnsRequestedCount(): void
    {
        self::assertCount(7, Hasher::deriveHashes('item', 7, 1000));
        self::assertCount(0, Hasher::deriveHashes('item', 0, 1000));
    }

    public function testDeriveHashesStayWithinModulus(): void
    {
        $mod = 257;
        foreach (Hasher::deriveHashes('some-item', 50, $mod) as $value) {
            self::assertGreaterThanOrEqual(0, $value);
            self::assertLessThan($mod, $value);
        }
    }

    public function testDeriveHashesIsDeterministic(): void
    {
        self::assertSame(
            Hasher::deriveHashes('repeatable', 12, 4096),
            Hasher::deriveHashes('repeatable', 12, 4096),
        );
    }

    public function testDeriveHashesDifferByItem(): void
    {
        self::assertNotSame(
            Hasher::deriveHashes('item-a', 10, 8192),
            Hasher::deriveHashes('item-b', 10, 8192),
        );
    }
}
