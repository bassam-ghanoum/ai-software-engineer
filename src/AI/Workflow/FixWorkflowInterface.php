<?php

declare(strict_types=1);

namespace App\AI\Workflow;

use App\AI\Agent\FixAgent\FixResult;
use App\AI\Review\DTO\ReviewBatch;

interface FixWorkflowInterface
{
    public function fix(
        ReviewBatch $reviews,
        bool $developerApproved,
    ): FixResult;
}