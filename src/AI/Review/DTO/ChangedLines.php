<?php

declare(strict_types=1);

namespace App\AI\Review\DTO;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;

/** @implements IteratorAggregate<string, ChangedLineNumbers> */
final readonly class ChangedLines implements Countable, IteratorAggregate, JsonSerializable
{
    /**
     * @param array<string, ChangedLineNumbers> $files
     */
    public function __construct(
        private array $files,
    ) {
        foreach ($files as $filePath => $lines) {
            if (!is_string($filePath) || $filePath === '' || !$lines instanceof ChangedLineNumbers) {
                throw new InvalidArgumentException(
                    'Changed lines must map non-empty file paths to ChangedLineNumbers.',
                );
            }
        }
    }

    public function count(): int
    {
        return count($this->files);
    }

    public function getFile(string $filePath): ?ChangedLineNumbers
    {
        return $this->files[$filePath] ?? null;
    }

    /** @return Traversable<string, ChangedLineNumbers> */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->files);
    }

    /** @return array<string, array<int, true>> */
    public function jsonSerialize(): array
    {
        $files = [];

        foreach ($this->files as $filePath => $lines) {
            $files[$filePath] = $lines->jsonSerialize();
        }

        return $files;
    }
}