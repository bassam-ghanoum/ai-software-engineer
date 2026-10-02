<?php

declare(strict_types=1);

namespace App\AI\DTO\Review;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/** @implements IteratorAggregate<int, bool> */
final readonly class ChangedLineNumbers implements Countable, IteratorAggregate, JsonSerializable
{
    /** @param array<int, true> $lines */
    public function __construct(
        private array $lines,
    ) {
        foreach ($lines as $line => $changed) {
            if (!is_int($line) || $line < 1 || $changed !== true) {
                throw new InvalidArgumentException(
                    'Changed line numbers must map positive line numbers to true.',
                );
            }
        }
    }

    public function count(): int
    {
        return count($this->lines);
    }

    public function contains(int $line): bool
    {
        return isset($this->lines[$line]);
    }

    public function nearestTo(int $line, int $maxDistance): ?int
    {
        $nearestLine = null;
        $nearestDistance = $maxDistance + 1;

        foreach ($this->lines as $changedLine => $_) {
            $distance = abs($changedLine - $line);

            if ($distance < $nearestDistance) {
                $nearestLine = $changedLine;
                $nearestDistance = $distance;
            }
        }

        return $nearestDistance <= $maxDistance ? $nearestLine : null;
    }

    /** @return Traversable<int, bool> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->lines);
    }

    /** @return array<int, true> */
    public function jsonSerialize(): array
    {
        return $this->lines;
    }
}