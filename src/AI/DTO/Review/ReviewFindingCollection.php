<?php

declare(strict_types=1);

namespace App\AI\DTO\Review;

use ArrayIterator;
use App\AI\Review\ReviewFinding;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use OutOfBoundsException;
use Traversable;

/** @implements IteratorAggregate<int, ReviewFinding> */
final readonly class ReviewFindingCollection implements Countable, IteratorAggregate
{
    /**
     * @param list<ReviewFinding> $findings
     */
    public function __construct(
        private array $findings,
    ) {
        if (!array_is_list($findings)) {
            throw new InvalidArgumentException('Review findings must be a list.');
        }

        foreach ($findings as $finding) {
            if (!$finding instanceof ReviewFinding) {
                throw new InvalidArgumentException(
                    'All review findings must be instances of ReviewFinding.',
                );
            }
        }
    }

    public function count(): int
    {
        return count($this->findings);
    }

    public function isEmpty(): bool
    {
        return $this->findings === [];
    }

    public function get(int $index): ReviewFinding
    {
        if (!array_key_exists($index, $this->findings)) {
            throw new OutOfBoundsException(sprintf(
                'No review finding exists at index %d.',
                $index,
            ));
        }

        return $this->findings[$index];
    }

    /** @return Traversable<int, ReviewFinding> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->findings);
    }
}