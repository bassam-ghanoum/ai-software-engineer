<?php

declare(strict_types=1);

namespace App\AI\Git;

interface ChangedCodeProviderInterface
{
    /**
     * @return array<string, string>
     */
    public function getChangedPhpFiles(
        string $from,
        string $to
    ): array;
}
