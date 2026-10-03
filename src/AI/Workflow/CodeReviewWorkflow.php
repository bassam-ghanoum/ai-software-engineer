<?php

declare(strict_types=1);

namespace App\AI\Workflow;

use App\AI\Agent\ReviewAgent\CodeReviewAgentInterface;
use App\AI\DTO\Review\ReviewBatch;
use App\AI\Git\ChangedCodeProviderInterface;
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

        /** @var array<string, ReviewResult|null> $resultsByFile */
        $resultsByFile = [];

        foreach ($changedFiles as $filePath => $_sourceCode) {
            $resultsByFile[$filePath] = null;
        }

        foreach ($this->requestPlanner->plan($changedFiles) as $batch) {
            $unitResults = $this->reviewAgent->reviewBatch($batch);

            foreach ($batch as $unit) {
                $result = $unitResults->getResult($unit->id);

                if (!$result instanceof ReviewResult) {
                    throw new \RuntimeException(sprintf(
                        'Missing review result for unit "%s".',
                        $unit->id,
                    ));
                }

                $previousResult = $resultsByFile[$unit->filePath];

                if ($previousResult === null) {
                    $resultsByFile[$unit->filePath] = $result;
                    continue;
                }

                $resultsByFile[$unit->filePath] = new ReviewResult([
                    ...iterator_to_array($previousResult->getFindings()),
                    ...iterator_to_array($result->getFindings()),
                ]);
            }
        }

        $results = [];

        foreach ($resultsByFile as $filePath => $result) {
            $results[$filePath] = $result ?? new ReviewResult([]);
        }

        return new ReviewBatch($results);
    }
}
