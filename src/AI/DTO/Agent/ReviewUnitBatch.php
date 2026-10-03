<?php

declare(strict_types=1);

namespace App\AI\DTO\Agent;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, ReviewUnit> */
final readonly class ReviewUnitBatch implements Countable, IteratorAggregate
{
    /** @param list<ReviewUnit> $units */
    public function __construct(
        private array $units,
    ) {
        if (!array_is_list($units)) {
            throw new InvalidArgumentException('Review units must be provided as a list.');
        }

        $ids = [];

        foreach ($units as $unit) {
            if (!$unit instanceof ReviewUnit || isset($ids[$unit->id])) {
                throw new InvalidArgumentException(
                    'A review unit batch must contain uniquely identified ReviewUnit objects.',
                );
            }

            $ids[$unit->id] = true;
        }
    }

    public function count(): int
    {
        return count($this->units);
    }

    public function isEmpty(): bool
    {
        return $this->units === [];
    }

    /** @return Traversable<int, ReviewUnit> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->units);
    }
}
