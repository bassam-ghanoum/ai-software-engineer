<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

use App\AI\Agent\PromptTemplateLoader;
use App\AI\LLM\LlmInterface;
use App\AI\Review\ReviewResult;
use RuntimeException;

final class LlmFixScopeValidator implements FixScopeValidatorInterface
{
    private const TEMPLATE_NAME = 'fix_scope_validator.txt';

    public function __construct(
        private readonly LlmInterface $llm,
        private readonly PromptTemplateLoader $promptTemplateLoader,
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

        if (
            !is_array($data)
            || !array_key_exists('approved', $data)
            || !is_bool($data['approved'])
            || !array_key_exists('reason', $data)
            || !is_string($data['reason'])
            || array_diff(array_keys($data), ['approved', 'reason']) !== []
        ) {
            throw new RuntimeException(
                'The fix scope validator returned an invalid response structure.',
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

        return $this->promptTemplateLoader->render(
            self::TEMPLATE_NAME,
            [
                '%%FILE_PATH%%' => $filePath,
                '%%REVIEW_FINDINGS%%' => $reviewText,
                '%%ORIGINAL_SOURCE%%' => $originalSource,
                '%%FIXED_SOURCE%%' => $fixedSource,
            ],
        );
    }
}