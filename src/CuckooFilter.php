<?php

declare(strict_types=1);

namespace Probabilistic;

use Probabilistic\Exception\FilterFullException;
use Probabilistic\Support\Hasher;

/**
 * Space-efficient probabilistic set membership testing with support
 * for deletion, using partial-key cuckoo hashing.
 *
 * Reference: Fan, B., Andersen, D. G., Kaminsky, M., & Mitzenmacher, M.
 * (2014). "Cuckoo Filter: Practically Better Than Bloom."
 * Proceedings of the 10th ACM CoNEXT Conference.
 */
final class CuckooFilter
{
    private const BUCKET_SIZE = 4;
    private const MAX_KICKS = 500;
    private const FINGERPRINT_BITS = 8; // 1 byte, values 1-255 (0 reserved for "empty")

    /** @var array<int, int[]> bucketIndex => list of fingerprints (each a slot) */
    private array $buckets;
    private readonly int $bucketCount;

    private function __construct(int $bucketCount)
    {
        $this->bucketCount = $bucketCount;
        $this->buckets = array_fill(0, $bucketCount, []);
    }

    public static function create(int $expectedItems): self
    {
        if ($expectedItems < 1) {
            throw new \InvalidArgumentException('expectedItems must be at least 1.');
        }
        // Target load factor ~95% per the reference paper, rounded
        // up to a power of two for clean modulo-free index wrapping.
        $bucketsNeeded = (int) ceil($expectedItems / self::BUCKET_SIZE / 0.95);
        $bucketCount = self::nextPowerOfTwo(max(1, $bucketsNeeded));

        return new self($bucketCount);
    }

    public function add(string $item): void
    {
        $fingerprint = $this->fingerprint($item);
        $i1 = $this->primaryIndex($item);
        $i2 = $this->alternateIndex($i1, $fingerprint);

        if ($this->insertIntoBucket($i1, $fingerprint) || $this->insertIntoBucket($i2, $fingerprint)) {
            return;
        }

        // Both candidate buckets are full — start the eviction dance.
        //
        // Eviction relocation is randomised (random_int / array_rand), so two
        // runs that insert the same items can lay them out differently. That
        // randomness is deliberately not seedable here: the public surface is
        // just create(int), and the behaviours that matter — an added item is
        // always found, a removed one is gone, and an over-full filter throws
        // rather than looping forever — hold regardless of which slot is
        // evicted. The probabilistic property (false-positive rate) is checked
        // statistically over many items, the same way the Bloom filters are.
        $index = random_int(0, 1) === 0 ? $i1 : $i2;
        for ($kick = 0; $kick < self::MAX_KICKS; $kick++) {
            $slotIndex = array_rand($this->buckets[$index]);
            $evicted = $this->buckets[$index][$slotIndex];
            $this->buckets[$index][$slotIndex] = $fingerprint;
            $fingerprint = $evicted;

            $index = $this->alternateIndex($index, $fingerprint);
            if ($this->insertIntoBucket($index, $fingerprint)) {
                return;
            }
        }

        throw new FilterFullException(
            'Cuckoo filter is full after ' . self::MAX_KICKS . ' eviction attempts. ' .
            'Create a larger filter (increase expectedItems).'
        );
    }

    public function contains(string $item): bool
    {
        $fingerprint = $this->fingerprint($item);
        $i1 = $this->primaryIndex($item);
        $i2 = $this->alternateIndex($i1, $fingerprint);

        return in_array($fingerprint, $this->buckets[$i1], true)
            || in_array($fingerprint, $this->buckets[$i2], true);
    }

    public function remove(string $item): bool
    {
        $fingerprint = $this->fingerprint($item);
        $i1 = $this->primaryIndex($item);
        $i2 = $this->alternateIndex($i1, $fingerprint);

        return $this->removeFromBucket($i1, $fingerprint)
            || $this->removeFromBucket($i2, $fingerprint);
    }

    private function insertIntoBucket(int $index, int $fingerprint): bool
    {
        if (count($this->buckets[$index]) >= self::BUCKET_SIZE) {
            return false;
        }
        $this->buckets[$index][] = $fingerprint;
        return true;
    }

    private function removeFromBucket(int $index, int $fingerprint): bool
    {
        $slot = array_search($fingerprint, $this->buckets[$index], true);
        if ($slot === false) {
            return false;
        }
        unset($this->buckets[$index][$slot]);
        $this->buckets[$index] = array_values($this->buckets[$index]);
        return true;
    }

    private function primaryIndex(string $item): int
    {
        return Hasher::fnv1a($item) % $this->bucketCount;
    }

    /**
     * The XOR trick: given one bucket index and a fingerprint, compute
     * the other candidate bucket index — works in both directions,
     * which is what makes cuckoo eviction possible without re-hashing
     * the original item.
     *
     * This is only a true involution (alt(alt(i)) == i) because
     * bucketCount is always a power of two, which makes `% bucketCount`
     * equivalent to masking the low bits — so XOR-ing the same
     * fingerprint hash a second time exactly undoes the first.
     */
    private function alternateIndex(int $index, int $fingerprint): int
    {
        $fingerprintHash = Hasher::fnv1a((string) $fingerprint);
        return ($index ^ $fingerprintHash) % $this->bucketCount;
    }

    /**
     * A non-zero 8-bit fingerprint derived from the item. Zero is
     * reserved to mean "empty slot" so it is never produced here.
     */
    private function fingerprint(string $item): int
    {
        $hash = Hasher::crc32Hash($item) & ((1 << self::FINGERPRINT_BITS) - 1);
        return $hash === 0 ? 1 : $hash;
    }

    private static function nextPowerOfTwo(int $n): int
    {
        $power = 1;
        while ($power < $n) {
            $power <<= 1;
        }
        return $power;
    }
}
