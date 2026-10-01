<?php

declare(strict_types=1);

namespace App\AI\Review\DTO;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, string> */
final readonly class ResolvedFingerprints implements Countable, IteratorAggregate
{
    /** @var array<string, true> */
    private array $fingerprints;

    /** @param list<string> $fingerprints */
    public function __construct(array $fingerprints)
    {
        if (!array_is_list($fingerprints)) {
            throw new InvalidArgumentException('Resolved fingerprints must be a list.');
        }

        $set = [];

        foreach ($fingerprints as $fingerprint) {
            if (!is_string($fingerprint) || preg_match('/^[a-f0-9]{64}$/', $fingerprint) !== 1) {
                throw new InvalidArgumentException(
                    'Resolved fingerprints must be lowercase SHA-256 strings.',
                );
            }

            $set[$fingerprint] = true;
        }

        $this->fingerprints = $set;
    }

    public function count(): int
    {
        return count($this->fingerprints);
    }

    public function contains(string $fingerprint): bool
    {
        return isset($this->fingerprints[$fingerprint]);
    }

    /** @return Traversable<int, string> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator(array_keys($this->fingerprints));
    }
}