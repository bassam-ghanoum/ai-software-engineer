<?php

declare(strict_types=1);

namespace App\AI\DTO\Agent\FixAgent;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/** @implements IteratorAggregate<string, string> */
final readonly class FixedFiles implements Countable, IteratorAggregate
{
    /**
     * @param array<string, string> $files File paths mapped to fixed source contents.
     */
    public function __construct(
        private array $files,
    ) {
        foreach ($files as $filePath => $source) {
            if (!is_string($filePath) || $filePath === '' || !is_string($source)) {
                throw new InvalidArgumentException(
                    'Fixed files must map non-empty paths to source strings.',
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

    public function getContent(string $filePath): ?string
    {
        return $this->files[$filePath] ?? null;
    }

    /** @return Traversable<string, string> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->files);
    }
}