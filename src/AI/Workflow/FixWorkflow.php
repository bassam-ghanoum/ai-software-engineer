<?php

declare(strict_types=1);

namespace App\AI\Workflow;

use App\AI\Agent\FixAgent\FixAgentInterface;
use App\AI\Agent\FixAgent\FixResult;
use App\AI\Agent\FixAgent\FixScopeValidatorInterface;
use App\AI\File\SourceFileProviderInterface;
use App\AI\File\SourceValidatorInterface;
use App\AI\Review\ReviewResult;
use InvalidArgumentException;
use Throwable;

final class FixWorkflow implements FixWorkflowInterface
{
    private const MAX_FIX_ATTEMPTS = 3;

    public function __construct(
        private readonly FixAgentInterface $fixAgent,
        private readonly SourceFileProviderInterface $sourceFileProvider,
        private readonly SourceValidatorInterface $sourceValidator,
        private readonly FixScopeValidatorInterface $fixScopeValidator,
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

        $validatedFiles = [];

        foreach ($reviews as $filePath => $reviewResult) {
            $fixedSource = $this->prepareFix(
                $filePath,
                $reviewResult,
            );

            if ($fixedSource === null) {
                continue;
            }

            $validatedFiles[$filePath] = $fixedSource;
        }

        $fixedFiles = [];

        foreach ($validatedFiles as $filePath => $fixedSource) {
            $this->sourceFileProvider->write(
                $filePath,
                $fixedSource,
            );

            echo sprintf(
                "Fix accepted for %s.\n",
                $filePath,
            );

            $fixedFiles[$filePath] = $fixedSource;
        }

        return new FixResult($fixedFiles);
    }

    private function prepareFix(
        string $filePath,
        ReviewResult $reviewResult,
    ): ?string {
        if (!$reviewResult->hasFindings()) {
            return null;
        }

        if (!$this->sourceFileProvider->exists($filePath)) {
            throw new InvalidArgumentException(
                sprintf('Source file does not exist: %s', $filePath),
            );
        }

        $sourceCode = $this->sourceFileProvider->read($filePath);

        $previousFailure = null;

        for ($attempt = 1; $attempt <= self::MAX_FIX_ATTEMPTS; $attempt++) {
            echo sprintf(
                "\nFixing: %s (attempt %d/%d)\n",
                $filePath,
                $attempt,
                self::MAX_FIX_ATTEMPTS,
            );

            try {
                $fixedSource = $this->fixAgent->fix(
                    $filePath,
                    $sourceCode,
                    $reviewResult,
                    $previousFailure,
                );

                echo sprintf(
                    "\n--- AI-Code-Fix-agent generated source for %s ---\n",
                    $filePath,
                );

                echo $fixedSource;

                echo sprintf(
                    "\n--- End AI-Code-Fix-agent generated source for %s ---\n\n",
                    $filePath,
                );

                if ($fixedSource === $sourceCode) {
                    echo sprintf(
                        "No changes generated for %s.\n",
                        $filePath,
                    );

                    return null;
                }

                /*
                 * Syntax validation happens first.
                 *
                 * This is intentionally before the LLM scope validator so
                 * that an invalid PHP result does not consume another LLM
                 * validation request.
                 */
                $this->sourceValidator->validate(
                    $filePath,
                    $fixedSource,
                );

                /*
                 * The scope validator ensures that AI-Code-Fix-agent actually
                 * satisfies the approved review and does not make
                 * unrelated changes.
                 */
                $this->fixScopeValidator->validate(
                    $filePath,
                    $sourceCode,
                    $fixedSource,
                    $reviewResult,
                );

                return $fixedSource;
            } catch (Throwable $exception) {
                $previousFailure = $exception->getMessage();

                echo sprintf(
                    "Fix attempt %d/%d failed for %s: %s\n",
                    $attempt,
                    self::MAX_FIX_ATTEMPTS,
                    $filePath,
                    $previousFailure,
                );

                if ($attempt === self::MAX_FIX_ATTEMPTS) {
                    throw $exception;
                }

                echo sprintf(
                    "Retrying AI-Code-Fix-Agent for %s...\n",
                    $filePath,
                );
            }
        }

        return null;
    }
}