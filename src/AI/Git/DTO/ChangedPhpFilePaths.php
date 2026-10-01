<?php

declare(strict_types=1);

namespace App\AI\Git\DTO;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<int, string> */
final readonly class ChangedPhpFilePaths implements Countable, IteratorAggregate
{
    /**
     * @param list<string> $paths
     */
    public function __construct(
        private array $paths,
    ) {
        if (!array_is_list($paths)) {
            throw new InvalidArgumentException('Changed file paths must be a list.');
        }

        foreach ($paths as $path) {
            if (!is_string($path) || $path === '') {
                throw new InvalidArgumentException('Changed file paths must be non-empty strings.');
            }
        }
    }

    public function count(): int
    {
        return count($this->paths);
    }

    public function isEmpty(): bool
    {
        return $this->paths === [];
    }

    /** @return Traversable<int, string> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->paths);
    }
}