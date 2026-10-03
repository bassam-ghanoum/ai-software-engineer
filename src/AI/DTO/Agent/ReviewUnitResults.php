<?php

declare(strict_types=1);

namespace App\AI\DTO\Agent;

use ArrayIterator;
use App\AI\Review\ReviewResult;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<string, ReviewResult> */
final readonly class ReviewUnitResults implements Countable, IteratorAggregate
{
    /** @param array<string, ReviewResult> $results Review unit IDs mapped to results. */
    public function __construct(
        private array $results,
    ) {
        foreach ($results as $unitId => $result) {
            if (
                !is_string($unitId)
                || $unitId === ''
                || !$result instanceof ReviewResult
            ) {
                throw new InvalidArgumentException(
                    'Review unit results must map non-empty IDs to ReviewResult objects.',
                );
            }
        }
    }

    public function count(): int
    {
        return count($this->results);
    }

    public function getResult(string $unitId): ?ReviewResult
    {
        return $this->results[$unitId] ?? null;
    }

    /** @return Traversable<string, ReviewResult> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->results);
    }
}
