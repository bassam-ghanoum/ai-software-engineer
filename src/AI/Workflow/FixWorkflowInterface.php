<?php

declare(strict_types=1);

namespace App\AI\Workflow;

use App\AI\Agent\FixAgent\FixResult;
use App\AI\Review\ReviewResult;

interface FixWorkflowInterface
{
    /**
     * @param array<string, ReviewResult> $reviews
     */
    public function fix(
        array $reviews,
        bool $developerApproved,
    ): FixResult;
}