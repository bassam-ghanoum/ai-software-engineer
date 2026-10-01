<?php

declare(strict_types=1);

namespace App\AI\Review\DTO;

use ArrayIterator;
use App\AI\Review\ReviewResult;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<string, ReviewResult> */
final readonly class ReviewBatch implements Countable, IteratorAggregate
{
    /**
     * @param array<string, ReviewResult> $reviews File paths mapped to review results.
     */
    public function __construct(
        private array $reviews,
    ) {
        foreach ($reviews as $filePath => $review) {
            if (
                !is_string($filePath)
                || $filePath === ''
                || !$review instanceof ReviewResult
            ) {
                throw new InvalidArgumentException(
                    'A review batch must map non-empty file paths to ReviewResult objects.',
                );
            }
        }
    }

    public function count(): int
    {
        return count($this->reviews);
    }

    public function isEmpty(): bool
    {
        return $this->reviews === [];
    }

    public function getReview(string $filePath): ?ReviewResult
    {
        return $this->reviews[$filePath] ?? null;
    }

    /** @return Traversable<string, ReviewResult> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->reviews);
    }
}