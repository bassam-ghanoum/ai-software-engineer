<?php

declare(strict_types=1);

namespace App\AI\Workflow;

use App\AI\Agent\FixAgent\FixAgentInterface;
use App\AI\Agent\FixAgent\FixResult;
use App\AI\File\SourceFileProviderInterface;
use App\AI\Review\ReviewResult;
use InvalidArgumentException;

final class FixWorkflow
{
    public function __construct(
        private readonly FixAgentInterface $fixAgent,
        private readonly SourceFileProviderInterface $sourceFileProvider,
    ) {
    }

    /**
     * Apply fixes one file at a time, only after explicit developer approval.
     *
     * @param array<string, ReviewResult> $reviews
     */
    public function fix(
        array $reviews,
        bool $developerApproved,
    ): FixResult {
        if (!$developerApproved) {
            return new FixResult([]);
        }

        $fixedFiles = [];

        foreach ($reviews as $filePath => $reviewResult) {
            $fixedSource = $this->fixFile(
                $filePath,
                $reviewResult,
            );

            if ($fixedSource === null) {
                continue;
            }

            $fixedFiles[$filePath] = $fixedSource;
        }

        return new FixResult($fixedFiles);
    }

    private function fixFile(
        string $filePath,
        ReviewResult $reviewResult,
    ): ?string {
        if (!$reviewResult->hasFindings()) {
            return null;
        }

        if (!$this->sourceFileProvider->exists($filePath)) {
            throw new InvalidArgumentException(
                sprintf('Source file does not exist: %s', $filePath)
            );
        }

        $sourceCode = $this->sourceFileProvider->read($filePath);

        $fixedSource = $this->fixAgent->fix(
            $filePath,
            $sourceCode,
            $reviewResult,
        );

        if ($fixedSource === $sourceCode) {
            return null;
        }

        $this->sourceFileProvider->write(
            $filePath,
            $fixedSource,
        );

        return $fixedSource;
    }
}