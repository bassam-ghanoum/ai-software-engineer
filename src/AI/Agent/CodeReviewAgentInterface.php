<?php

namespace App\AI\Agent;

use App\AI\Review\ReviewResult;

interface CodeReviewAgentInterface
{
    public function review(string $filePath, string $code): ReviewResult;

    /**
     * @param list<ReviewUnit> $units
     * @return array<string, ReviewResult> Results keyed by review unit ID.
     */
    public function reviewBatch(array $units): array;
}
