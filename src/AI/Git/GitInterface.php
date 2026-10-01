<?php

declare(strict_types=1);

namespace App\AI\Git;

use App\AI\Git\DTO\ChangedPhpFilePaths;

interface GitInterface
{
    /** @return ChangedPhpFilePaths containing repository-relative file paths. */
    public function getChangedPhpFiles(
        string $from,
        string $to
    ): ChangedPhpFilePaths;

    public function readFile(string $path): string;

    public function readFileAtRevision(string $path, string $revision): string;

    public function assertWorkingTreeMatchesRevision(string $revision): void;
}
