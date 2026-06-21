# probabilistic-php

Pure-PHP probabilistic data structures: **Bloom Filter**, **Counting Bloom Filter**, **Cuckoo Filter**, **Count-Min Sketch**, and **HyperLogLog**. No runtime dependencies, no Redis — everything runs in plain PHP memory.

## Why this exists

JavaScript has [`bloom-filters`](https://www.npmjs.com/package/bloom-filters) and Go has [`BoomFilters`](https://github.com/tylertreat/BoomFilters) — single, well-maintained libraries covering this whole family. PHP has had no equivalent: the existing Packagist options are either a bare Bloom filter, abandoned since the PHP 5.x–7.1 era, or require a running Redis server to do anything at all. This package fills that gap with a modern, fully tested, pure in-memory implementation for PHP 8.2+.

## These are *probabilistic* structures

Every structure here trades a little accuracy for a large amount of memory. That is the point, not a bug:

- A **Bloom / Counting Bloom / Cuckoo** filter answers *"have I probably seen this?"* It never reports a false negative, but it may report a false positive at a rate you configure.
- A **Count-Min Sketch** answers *"roughly how many times?"* It never undercounts, but it may overcount.
- A **HyperLogLog** answers *"roughly how many distinct things?"* with a small, statistically bounded error (roughly 0.3–3.6% at the default precision in testing), not an exact count.

If you need exact answers, use a real `Set` or a database. If you need to track membership or counts over millions of items in kilobytes of memory, these are the right tool.

## Installation

```bash
composer require pouya1364/probabilistic-php
```

Requires PHP 8.2+ and the `ext-hash` extension (bundled with PHP core).

## Usage

```php
use Probabilistic\BloomFilter;
use Probabilistic\CountingBloomFilter;
use Probabilistic\CuckooFilter;
use Probabilistic\CountMinSketch;
use Probabilistic\HyperLogLog;
```

### Bloom Filter — membership testing, no false negatives

```php
$bloom = BloomFilter::create(expectedItems: 10_000, falsePositiveRate: 0.01);
$bloom->add('user@example.com');

$bloom->mightContain('user@example.com'); // true
$bloom->mightContain('nobody@example.com'); // false (probably)
```

`mightContain()` returning `false` is a guarantee the item was never added. `true` means it probably was, with at most the configured false-positive rate.

### Counting Bloom Filter — Bloom Filter with removal

```php
$counting = CountingBloomFilter::create(expectedItems: 10_000, falsePositiveRate: 0.01);
$counting->add('session-abc');
$counting->remove('session-abc');

$counting->mightContain('session-abc'); // false
```

Each slot is a counter (capped at 255) instead of a single bit, which is what allows removal. Removing an item that was never added throws — it would corrupt the counts of other items sharing those slots.

### Cuckoo Filter — space-efficient membership with removal

```php
$cuckoo = CuckooFilter::create(expectedItems: 10_000);
$cuckoo->add('192.168.1.1');

$cuckoo->contains('192.168.1.1'); // true
$cuckoo->remove('192.168.1.1'); // true
```

Often more space-efficient than a Bloom filter for the same false-positive rate, with deletion built in. A `FilterFullException` is thrown if the filter is overfilled well past its `expectedItems` — create a larger one.

### Count-Min Sketch — approximate frequency counting

```php
$cms = CountMinSketch::create(width: 2_000, depth: 5);
$cms->increment('page:/home');
$cms->increment('page:/home');

$cms->estimate('page:/home'); // 2 (or slightly higher, never lower)
```

Counts are never underestimated. Wider/deeper sketches reduce overestimation at the cost of memory. Two sketches of identical dimensions can be combined with `merge()`.

### HyperLogLog — approximate distinct-count (cardinality)

```php
$hll = new HyperLogLog(precision: 14); // ~16 KB of memory

foreach ($visitorIds as $id) {
    $hll->add($id);
}

$hll->estimate(); // approximately the number of distinct visitor IDs
```

Estimates the cardinality of a stream using a tiny, fixed amount of memory regardless of how many items pass through. Two estimators of equal precision can be combined with `merge()`, giving exactly what a single estimator over the union would have produced.

## Choosing a structure

| You want to… | Use | Removal? | Error |
|---|---|---|---|
| Test membership in minimal memory | `BloomFilter` | No | Configurable false positives |
| …and also remove items | `CountingBloomFilter` | Yes | Configurable false positives |
| …with better space efficiency and removal | `CuckooFilter` | Yes | Low, fixed false positives |
| Count how often each item appears | `CountMinSketch` | No | Never under, may over |
| Count how many *distinct* items appear | `HyperLogLog` | No | 0.3–3.6% at precision 14 |

## Error handling

Every exception this library throws implements `Probabilistic\Exception\ExceptionInterface`, so you can catch all of them in one place:

```php
use Probabilistic\Exception\ExceptionInterface;

try {
    $bloom = BloomFilter::create(expectedItems: 0, falsePositiveRate: 0.01);
} catch (ExceptionInterface $e) {
    // any error originating from this package
}
```

Each one also extends the closest SPL exception — for example `InvalidConfigurationException extends \InvalidArgumentException` — so existing `catch` blocks for the standard types keep working.

## Testing

```bash
composer check   # static analysis (PHPStan level 8) + lint (PSR-12) + tests
```

Because these structures are probabilistic, the test suite mixes two styles: exact assertions for the hard guarantees (a Bloom filter never reports a false negative; a Count-Min Sketch never underestimates) and statistical-tolerance assertions for the rest (observed false-positive and error rates stay within generous, meaningful bounds across many items). If you contribute, follow the same pattern rather than asserting exact equality on inherently approximate outputs.

## Contributing

`composer.lock` is intentionally **not** committed: this is a library, so consumers resolve their own compatible dependency versions. Run `composer check` before opening a pull request — analysis, lint, and tests must all pass.

## Framework integrations

Ready-made wiring to use these structures as named, pre-configured services in your framework of choice:

- [probabilistic-laravel](https://github.com/pouya1364/probabilistic-laravel) — Laravel integration via the service container and a Facade.
- [probabilistic-bundle](https://github.com/pouya1364/probabilistic-bundle) — Symfony bundle exposing them as configured services.

## Other projects

More of my packages are listed on my [GitHub profile](https://github.com/pouya1364).

## License

MIT — see [LICENSE](LICENSE).
