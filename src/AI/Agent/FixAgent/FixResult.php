<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

use App\AI\DTO\Agent\FixAgent\FixedFiles;

final class FixResult
{
    public function __construct(
        private readonly FixedFiles $fixedFiles,
    ) {
    }

    public function getFixedFiles(): FixedFiles
    {
        return $this->fixedFiles;
    }

    public function count(): int
    {
        return $this->fixedFiles->count();
    }

    public function hasChanges(): bool
    {
        return !$this->fixedFiles->isEmpty();
    }
}