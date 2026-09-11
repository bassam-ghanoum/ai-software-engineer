<?php

namespace App\AI\Workflow;

use App\AI\Review\ReviewResult;

interface CodeReviewWorkflowInterface
{
    /**
     * @return array<string, ReviewResult>
     */
    public function reviewChanges(
        string $from,
        string $to
    ): array;
}
