<?php

declare(strict_types=1);

namespace App\AI\DTO\Workflow;

use ArrayIterator;
use App\AI\DTO\Agent\ReviewUnitBatch;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, ReviewUnitBatch> */
final readonly class ReviewRequestPlan implements Countable, IteratorAggregate
{
    /** @param list<ReviewUnitBatch> $batches */
    public function __construct(
        private array $batches,
    ) {
        if (!array_is_list($batches)) {
            throw new InvalidArgumentException('Review request batches must be provided as a list.');
        }

        foreach ($batches as $batch) {
            if (!$batch instanceof ReviewUnitBatch || $batch->isEmpty()) {
                throw new InvalidArgumentException(
                    'A review request plan must contain non-empty ReviewUnitBatch objects.',
                );
            }
        }
    }

    public function count(): int
    {
        return count($this->batches);
    }

    public function isEmpty(): bool
    {
        return $this->batches === [];
    }

    /** @return Traversable<int, ReviewUnitBatch> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->batches);
    }
}
