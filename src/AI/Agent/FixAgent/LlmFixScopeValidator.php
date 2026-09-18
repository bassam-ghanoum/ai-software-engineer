<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

use App\AI\LLM\LlmInterface;
use App\AI\Review\ReviewResult;
use RuntimeException;

final class LlmFixScopeValidator implements FixScopeValidatorInterface
{
    public function __construct(
        private readonly LlmInterface $llm,
    ) {
    }

    public function validate(
        string $filePath,
        string $originalSource,
        string $fixedSource,
        ReviewResult $reviewResult,
    ): void {
        $prompt = $this->buildPrompt(
            $filePath,
            $originalSource,
            $fixedSource,
            $reviewResult,
        );

        $response = $this->llm->generateJson($prompt);

        try {
            /** @var array{approved?: bool, reason?: string} $data */
            $data = json_decode(
                $this->extractJson($response),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $exception) {
            throw new RuntimeException(
                'The fix scope validator returned invalid JSON.',
                0,
                $exception,
            );
        }

        if (($data['approved'] ?? false) !== true) {
            throw new RuntimeException(sprintf(
                'Fix rejected because it contains changes outside the review scope: %s',
                $data['reason'] ?? 'No reason provided.',
            ));
        }
    }

    private function extractJson(string $response): string
    {
        $response = trim($response);

        if (
            str_starts_with($response, '```json')
            && str_ends_with($response, '```')
        ) {
            return trim(substr($response, 7, -3));
        }

        if (
            str_starts_with($response, '```')
            && str_ends_with($response, '```')
        ) {
            return trim(substr($response, 3, -3));
        }

        $jsonStart = strpos($response, '{');
        $jsonEnd = strrpos($response, '}');

        if ($jsonStart !== false && $jsonEnd !== false && $jsonEnd >= $jsonStart) {
            return substr($response, $jsonStart, $jsonEnd - $jsonStart + 1);
        }

        return $response;
    }

    private function buildPrompt(
        string $filePath,
        string $originalSource,
        string $fixedSource,
        ReviewResult $reviewResult,
    ): string {
        $review = [];

        foreach ($reviewResult->getFindings() as $finding) {
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
You are a strict code-change scope validator.

Your task is to determine whether the fixed source code changes ONLY what is necessary
to address the supplied code review findings.

Rules:
1. Approve the fix only if every change is related to the review findings.
2. Reject unrelated refactoring.
3. Reject formatting-only changes unless required by the fix.
4. Reject changes to comments unless required by the fix.
5. Reject changes to return values or behavior unless required by the review findings.
6. Preserve unrelated functionality exactly.
7. If uncertain, reject the fix.
8. Return ONLY valid JSON.
9. Do not wrap the JSON in Markdown code fences.
10. The JSON must have exactly this structure:
{
  "approved": true,
  "reason": "short explanation"
}

File:
{$filePath}

Code review findings:
{$reviewText}

Original source:
{$originalSource}

Fixed source:
{$fixedSource}

Determine whether the fixed source is within the scope of the review findings.
PROMPT;
    }
}