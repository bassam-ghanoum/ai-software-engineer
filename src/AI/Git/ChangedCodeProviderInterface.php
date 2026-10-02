<?php

declare(strict_types=1);

namespace App\AI\Git;

use App\AI\DTO\Git\ChangedPhpFiles;

interface ChangedCodeProviderInterface
{
    /** @return ChangedPhpFiles mapping repository-relative paths to source. */
    public function getChangedPhpFiles(
        string $from,
        string $to
    ): ChangedPhpFiles;
}
