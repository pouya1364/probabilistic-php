<?php

declare(strict_types=1);

namespace Probabilistic\Support;

/**
 * Non-cryptographic hashing utility.
 *
 * Provides two independent 32-bit base hashes (FNV-1a and PHP's built-in
 * crc32), then derives any number of additional hash values from those two
 * using the Kirsch-Mitzenmacher double hashing technique:
 *
 *     h_i(x) = (h1(x) + i * h2(x)) mod m
 *
 * This avoids needing k independent hash functions while preserving the
 * uniformity guarantees the data structures above rely on.
 *
 * PHP-specific note: on 64-bit builds PHP integers are 64-bit, so 32-bit
 * intermediate values never silently overflow into float (which would
 * happen on 32-bit builds and silently corrupt results). Every step below
 * explicitly masks with & 0xFFFFFFFF to keep values within true 32-bit
 * unsigned range regardless of build, matching the FNV-1a reference
 * specification exactly.
 */
final class Hasher
{
    private const FNV_OFFSET_BASIS = 0x811c9dc5;
    private const FNV_PRIME = 0x01000193;

    /**
     * FNV-1a 32-bit hash. Reference: Fowler-Noll-Vo hash function, 1991.
     */
    public static function fnv1a(string $input): int
    {
        $hash = self::FNV_OFFSET_BASIS;
        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $hash ^= ord($input[$i]);
            $hash = ($hash * self::FNV_PRIME) & 0xFFFFFFFF;
        }
        return $hash;
    }

    /**
     * Second independent hash, using PHP's built-in CRC32.
     * crc32() and FNV-1a have different mathematical structure,
     * which is what double hashing requires to behave like independent
     * hash functions.
     */
    public static function crc32Hash(string $input): int
    {
        return crc32($input);
    }

    /**
     * 32-bit MurmurHash3. Unlike fnv1a above, this has strong avalanche,
     * which HyperLogLog depends on: its cardinality estimate is acutely
     * sensitive to non-uniformity in the hash, and FNV-1a's weaker bit
     * mixing skews the register distribution enough to bias the estimate by
     * double-digit percentages. Provided natively by ext-hash, which also
     * sidesteps MurmurHash3's 32-bit constants overflowing PHP's signed
     * 64-bit multiply if hand-rolled.
     */
    public static function murmur3a(string $input): int
    {
        return (int) hexdec(hash('murmur3a', $input));
    }

    /**
     * Derive `count` hash values for the given input, each reduced
     * into the range [0, $mod).
     *
     * @return int[] exactly `count` hash values
     */
    public static function deriveHashes(string $input, int $count, int $mod): array
    {
        $h1 = self::fnv1a($input);
        $h2 = self::crc32Hash($input);

        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $combined = ($h1 + $i * $h2) & 0xFFFFFFFF;
            $results[] = $combined % $mod;
        }
        return $results;
    }
}
