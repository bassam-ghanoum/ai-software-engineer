<?php

declare(strict_types=1);

namespace App\AI\Git\DTO;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<string, string> */
final readonly class ChangedPhpFiles implements Countable, IteratorAggregate
{
    /**
     * @param array<string, string> $files File paths mapped to source contents.
     */
    public function __construct(
        private array $files,
    ) {
        foreach ($files as $path => $source) {
            if (!is_string($path) || $path === '' || !is_string($source)) {
                throw new InvalidArgumentException(
                    'Changed PHP files must map non-empty paths to source strings.',
                );
            }
        }
    }

    public function count(): int
    {
        return count($this->files);
    }

    public function isEmpty(): bool
    {
        return $this->files === [];
    }

    public function getContent(string $path): ?string
    {
        return $this->files[$path] ?? null;
    }

    /** @return Traversable<string, string> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->files);
    }
}