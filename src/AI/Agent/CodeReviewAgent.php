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

        $prompt = <<<PROMPT
You are a senior PHP code reviewer.

Review the following PHP file.

File:
{$filePath}

Look for:
- Bugs
- Security vulnerabilities
- Performance problems
- Code smells
- Maintainability issues
- Missing validation
- Missing error handling

For every finding, identify the exact 1-based line number in the provided PHP source where the problem occurs.

The line number MUST refer to the actual PHP source code provided below.

Return ONLY valid JSON.

The JSON must have exactly this structure:

{
  "findings": [
    {
      "line": 5,
      "severity": "critical|high|medium|low",
      "category": "security|bug|performance|maintainability|validation|error_handling|code_smell",
      "message": "A concise explanation of the problem.",
      "suggestion": "A concise recommendation to fix the problem."
    }
  ]
}

Rules for line:
- "line" must be an integer.
- "line" must be greater than or equal to 1.
- "line" must point to the exact line containing the reviewed issue.
- Do not guess a line number.
- Use the line number from the provided PHP source.

If there are no findings, return:

{
  "findings": []
}

Do not include Markdown.
Do not include code fences.
Do not include any text outside the JSON.

PHP file:
----------------
$code
----------------
PROMPT;

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
