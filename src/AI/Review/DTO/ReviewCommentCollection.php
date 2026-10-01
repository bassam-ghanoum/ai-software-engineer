<?php

declare(strict_types=1);

namespace App\AI\Review\DTO;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use OutOfBoundsException;
use Traversable;

/** @implements IteratorAggregate<int, ReviewComment> */
final readonly class ReviewCommentCollection implements Countable, IteratorAggregate, JsonSerializable
{
    /** @param list<ReviewComment> $comments */
    public function __construct(
        private array $comments,
    ) {
        if (!array_is_list($comments)) {
            throw new InvalidArgumentException('Review comments must be a list.');
        }

        foreach ($comments as $comment) {
            if (!$comment instanceof ReviewComment) {
                throw new InvalidArgumentException(
                    'Review comments must contain only ReviewComment objects.',
                );
            }
        }
    }

    public function count(): int
    {
        return count($this->comments);
    }

    public function get(int $index): ReviewComment
    {
        if (!array_key_exists($index, $this->comments)) {
            throw new OutOfBoundsException(sprintf(
                'No review comment exists at index %d.',
                $index,
            ));
        }

        return $this->comments[$index];
    }

    /** @return Traversable<int, ReviewComment> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->comments);
    }

    /** @return list<ReviewComment> */
    public function jsonSerialize(): array
    {
        return $this->comments;
    }
}