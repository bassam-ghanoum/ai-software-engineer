<?php

declare(strict_types=1);

namespace App\AI\DTO\Git;

final readonly class GitCommandResult
{
    public function __construct(
        public string $output,
        public string $errorOutput,
        public int $exitCode,
    ) {
    }
}