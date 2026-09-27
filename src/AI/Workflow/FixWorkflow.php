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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

final class FixWorkflow implements FixWorkflowInterface
{
    private const MAX_FIX_ATTEMPTS = 3;

    public function __construct(
        private readonly FixAgentInterface $fixAgent,
        private readonly SourceFileProviderInterface $sourceFileProvider,
        private readonly SourceValidatorInterface $sourceValidator,
        private readonly FixScopeValidatorInterface $fixScopeValidator,
        private readonly LoggerInterface $logger = new NullLogger(),
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

            $this->logger->info('AI fix accepted.', ['file' => $filePath]);

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
            $this->logger->info('Attempting AI fix.', [
                'file' => $filePath,
                'attempt' => $attempt,
                'max_attempts' => self::MAX_FIX_ATTEMPTS,
            ]);

            try {
                $fixedSource = $this->fixAgent->fix(
                    $filePath,
                    $sourceCode,
                    $reviewResult,
                    $previousFailure,
                );

                if ($fixedSource === $sourceCode) {
                    $this->logger->info(
                        'AI fix produced no changes.',
                        ['file' => $filePath],
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

                $this->logger->warning('AI fix attempt failed.', [
                    'file' => $filePath,
                    'attempt' => $attempt,
                    'max_attempts' => self::MAX_FIX_ATTEMPTS,
                    'error' => $previousFailure,
                ]);

                if ($attempt === self::MAX_FIX_ATTEMPTS) {
                    throw $exception;
                }

                $this->logger->info('Retrying AI fix.', ['file' => $filePath]);
            }
        }

        return null;
    }
}