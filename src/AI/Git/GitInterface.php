<?php

declare(strict_types=1);

namespace App\AI\Git;

interface GitInterface
{
    /**
     * @return array<int, string>
     */
    public function getChangedPhpFiles(
        string $from,
        string $to
    ): array;

    public function readFile(string $path): string;

    public function readFileAtRevision(string $path, string $revision): string;
}
