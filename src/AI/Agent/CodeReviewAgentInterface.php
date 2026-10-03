<?php

namespace App\AI\Agent;

use App\AI\DTO\Agent\ReviewUnitBatch;
use App\AI\DTO\Agent\ReviewUnitResults;
use App\AI\Review\ReviewResult;

interface CodeReviewAgentInterface
{
    public function review(string $filePath, string $code): ReviewResult;

    public function reviewBatch(ReviewUnitBatch $batch): ReviewUnitResults;
}
