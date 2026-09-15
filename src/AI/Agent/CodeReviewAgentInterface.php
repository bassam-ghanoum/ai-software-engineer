<?php

namespace App\AI\Agent;

use App\AI\Review\ReviewResult;

interface CodeReviewAgentInterface
{
    public function review(string $filePath, string $code): ReviewResult;
}