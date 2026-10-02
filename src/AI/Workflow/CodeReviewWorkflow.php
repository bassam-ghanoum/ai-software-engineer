<?php

declare(strict_types=1);

namespace App\AI\Workflow;

use App\AI\Agent\CodeReviewAgentInterface;
use App\AI\DTO\Review\ReviewBatch;
use App\AI\Git\ChangedCodeProviderInterface;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;

final class CodeReviewWorkflow implements CodeReviewWorkflowInterface
{
    private readonly ReviewRequestPlanner $requestPlanner;

    public function __construct(
        private readonly ChangedCodeProviderInterface $changedCodeProvider,
        private readonly CodeReviewAgentInterface $reviewAgent,
        ?ReviewRequestPlanner $requestPlanner = null,
    ) {
        $this->requestPlanner = $requestPlanner ?? new ReviewRequestPlanner();
    }

    public function reviewChanges(
        string $from,
        string $to,
    ): ReviewBatch {
        $changedFiles = $this->changedCodeProvider->getChangedPhpFiles($from, $to);

        /** @var array<string, list<ReviewFinding>> $findingsByFile */
        $findingsByFile = [];

        foreach ($changedFiles as $filePath => $_sourceCode) {
            $findingsByFile[$filePath] = [];
        }

        foreach ($this->requestPlanner->plan($changedFiles) as $batch) {
            $unitResults = $this->reviewAgent->reviewBatch($batch);

            foreach ($batch as $unit) {
                $result = $unitResults[$unit->id] ?? null;

                if (!$result instanceof ReviewResult) {
                    throw new \RuntimeException(sprintf(
                        'Missing review result for unit "%s".',
                        $unit->id,
                    ));
                }

                foreach ($result->getFindings() as $finding) {
                    $findingsByFile[$unit->filePath][] = $finding;
                }
            }
        }

        $results = [];

        foreach ($findingsByFile as $filePath => $findings) {
            $results[$filePath] = new ReviewResult($findings);
        }

        return new ReviewBatch($results);
    }
}
