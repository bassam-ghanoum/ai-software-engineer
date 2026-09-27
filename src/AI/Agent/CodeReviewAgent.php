<?php

declare(strict_types=1);

namespace App\AI\Agent;

use App\AI\LLM\LlmInterface;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;

final class CodeReviewAgent implements CodeReviewAgentInterface
{
    public function __construct(
        private readonly LlmInterface $llm,
        private readonly PromptTemplateLoader $promptTemplateLoader,
    ) {
    }

    public function review(
        string $filePath,
        string $code,
    ): ReviewResult {
        if (trim($filePath) === '') {
            throw new \InvalidArgumentException('The file path cannot be empty.');
        }

        if ($code === '') {
            throw new \InvalidArgumentException('The code cannot be empty.');
        }

            $prompt = $this->promptTemplateLoader->render(
                'code_review.txt',
                [
                '%%FILE_PATH%%' => $filePath,
                '%%CODE%%' => $code,
                ],
            );

        $json = $this->llm->generateJson($prompt);

        try {
            $data = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                sprintf(
                    "The LLM returned invalid JSON.\nJSON error: %s\nRaw response:\n%s",
                    $exception->getMessage(),
                    $json,
                ),
                0,
                $exception,
            );
        }

        if (
            !is_array($data)
            || !array_key_exists('findings', $data)
            || !is_array($data['findings'])
        ) {
            throw new \RuntimeException(
                'The LLM JSON response does not contain a valid findings array.',
            );
        }

        $lineCount = count(preg_split('/\R/', $code));

        $findings = [];

        foreach ($data['findings'] as $finding) {
            if (!is_array($finding)) {
                throw new \RuntimeException(
                    'A review finding must be a JSON object.',
                );
            }

            $requiredFields = [
                'line',
                'severity',
                'category',
                'message',
                'suggestion',
            ];

            foreach ($requiredFields as $field) {
                if (!array_key_exists($field, $finding)) {
                    throw new \RuntimeException(
                        sprintf(
                            'Review finding field "%s" is missing or invalid.',
                            $field,
                        ),
                    );
                }
            }

            if (
                !is_int($finding['line'])
                || $finding['line'] < 1
                || $finding['line'] > $lineCount
            ) {
                throw new \RuntimeException(
                    'Review finding field "line" is missing or invalid.',
                );
            }

            foreach (
                [
                    'severity',
                    'category',
                    'message',
                    'suggestion',
                ] as $field
            ) {
                if (!is_string($finding[$field])) {
                    throw new \RuntimeException(
                        sprintf(
                            'Review finding field "%s" is missing or invalid.',
                            $field,
                        ),
                    );
                }
            }

            $findings[] = new ReviewFinding(
                line: $finding['line'],
                severity: $finding['severity'],
                category: $finding['category'],
                message: $finding['message'],
                suggestion: $finding['suggestion'],
            );
        }

        return new ReviewResult($findings);
    }
}
