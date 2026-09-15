<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

use App\AI\LLM\LlmInterface;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;

final class FixAgent implements FixAgentInterface
{
    public function __construct(
        private readonly LlmInterface $llm,
    ) {
    }

    public function fix(
        string $filePath,
        string $sourceCode,
        ReviewResult $reviewResult,
    ): string {
        if (!$reviewResult->hasFindings()) {
            return $sourceCode;
        }

        $prompt = $this->buildPrompt(
            $filePath,
            $sourceCode,
            $reviewResult->getFindings(),
        );

        return $this->llm->generate($prompt);
    }

    /**
     * @param ReviewFinding[] $findings
     */
    private function buildPrompt(
        string $filePath,
        string $sourceCode,
        array $findings,
    ): string {
        $review = [];

        foreach ($findings as $finding) {
            $review[] = sprintf(
                "- Severity: %s\n  Category: %s\n  Message: %s\n  Suggestion: %s",
                $finding->getSeverity(),
                $finding->getCategory(),
                $finding->getMessage(),
                $finding->getSuggestion(),
            );
        }

        $reviewText = implode("\n", $review);

        return <<<PROMPT
You are an AI software engineer responsible for fixing source code.

Your task is to fix ONLY the issues identified by the code review.

Rules:
1. Return ONLY the complete corrected source code.
2. Do not return Markdown code fences.
3. Do not explain the changes.
4. Do not modify unrelated code.
5. Preserve the existing functionality unless a review finding requires a change.
6. Do not introduce new dependencies unless absolutely necessary.
7. Keep the existing coding style.
8. The returned result must be valid source code.
9. Apply all applicable review findings.

File:
{$filePath}

Current source code:
{$sourceCode}

Code review findings:
{$reviewText}

Return the complete corrected source code only.
PROMPT;
    }
}