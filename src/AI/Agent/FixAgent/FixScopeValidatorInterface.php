<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

use App\AI\Review\ReviewResult;

interface FixScopeValidatorInterface
{
    public function validate(
        string $filePath,
        string $originalSource,
        string $fixedSource,
        ReviewResult $reviewResult,
    ): void;
}
