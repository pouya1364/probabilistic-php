# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-06-20

### Added

- `BloomFilter` — space-efficient membership testing with a configurable false-positive rate and no false negatives.
- `CountingBloomFilter` — Bloom filter variant supporting removal via per-slot counters.
- `CuckooFilter` — partial-key cuckoo hashing with built-in deletion and a `FilterFullException` overflow guard.
- `CountMinSketch` — approximate frequency counting that never underestimates, with sketch merging.
- `HyperLogLog` — approximate distinct-count estimation with mergeable estimators.
- `Support\Hasher` and `Support\BitArray` shared utilities.
- `Exception\ExceptionInterface` marker implemented by every package exception (`InvalidConfigurationException`, `CounterOverflowException`, `IndexOutOfRangeException`, `UnknownItemException`, `FilterFullException`), so all library errors can be caught together.
