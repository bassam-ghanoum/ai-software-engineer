<?php

namespace App\AI\Workflow;

use App\AI\Review\DTO\ReviewBatch;

interface CodeReviewWorkflowInterface
{
    public function reviewChanges(
        string $from,
        string $to
    ): ReviewBatch;
}
