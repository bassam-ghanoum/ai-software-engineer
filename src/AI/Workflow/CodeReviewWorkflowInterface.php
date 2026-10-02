<?php

namespace App\AI\Workflow;

use App\AI\DTO\Review\ReviewBatch;

interface CodeReviewWorkflowInterface
{
    public function reviewChanges(
        string $from,
        string $to
    ): ReviewBatch;
}
