<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

use App\AI\Review\ReviewResult;

interface FixAgentInterface
{
    public function fix(
        string $filePath,
        string $sourceCode,
        ReviewResult $reviewResult,
        ?string $previousFailure = null,
    ): string;
}