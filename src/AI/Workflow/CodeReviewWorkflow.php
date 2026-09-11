<?php

namespace App\AI\Workflow;

use App\AI\Agent\CodeReviewAgentInterface;
use App\AI\Git\ChangedCodeProviderInterface;
use App\AI\Review\ReviewResult;

final class CodeReviewWorkflow implements CodeReviewWorkflowInterface
{
    public function __construct(
        private readonly ChangedCodeProviderInterface $changedCodeProvider,
        private readonly CodeReviewAgentInterface $reviewAgent,
    ) {
    }

    /**
     * @return array<string, ReviewResult>
     */
    public function reviewChanges(
        string $from,
        string $to
    ): array {
        $changedFiles = $this->changedCodeProvider->getChangedPhpFiles(
            $from,
            $to
        );

        $results = [];

        foreach ($changedFiles as $path => $code) {
            $results[$path] = $this->reviewAgent->review($code);
        }

        return $results;
    }
}