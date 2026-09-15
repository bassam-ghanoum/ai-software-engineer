<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

final class FixResult
{
    /**
     * @param array<string, string> $fixedFiles
     */
    public function __construct(
        private readonly array $fixedFiles,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getFixedFiles(): array
    {
        return $this->fixedFiles;
    }

    public function count(): int
    {
        return count($this->fixedFiles);
    }

    public function hasChanges(): bool
    {
        return $this->fixedFiles !== [];
    }
}