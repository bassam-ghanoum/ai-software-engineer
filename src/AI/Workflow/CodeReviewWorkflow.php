<?php

declare(strict_types=1);

namespace App\AI\Workflow;

use App\AI\Agent\CodeReviewAgentInterface;
use App\AI\Git\ChangedCodeProviderInterface;
use App\AI\Review\DTO\ReviewBatch;

final class CodeReviewWorkflow implements CodeReviewWorkflowInterface
{
    public function __construct(
        private readonly ChangedCodeProviderInterface $changedCodeProvider,
        private readonly CodeReviewAgentInterface $reviewAgent,
    ) {
    }

    /** Review each changed PHP file independently. */
    public function reviewChanges(
        string $from,
        string $to,
    ): ReviewBatch {
        $changedFiles = $this->changedCodeProvider->getChangedPhpFiles(
            $from,
            $to,
        );

        $results = [];

        foreach ($changedFiles as $filePath => $sourceCode) {
            $results[$filePath] = $this->reviewAgent->review(
                $filePath,
                $sourceCode,
            );
        }

        return new ReviewBatch($results);
    }
}