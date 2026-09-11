<?php

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

    public function review(string $code): ReviewResult
    {
        $prompt = <<<PROMPT
You are a senior PHP code reviewer.

Review the following PHP code.

Look for:
- Bugs
- Security vulnerabilities
- Performance problems
- Code smells
- Maintainability issues
- Missing validation
- Missing error handling

Return ONLY valid JSON.

The JSON must have exactly this structure:

{
  "findings": [
    {
      "severity": "critical|high|medium|low",
      "category": "security|bug|performance|maintainability|validation|error_handling|code_smell",
      "message": "A concise explanation of the problem.",
      "suggestion": "A concise recommendation to fix the problem."
    }
  ]
}

If there are no findings, return:

{
  "findings": []
}

Do not include Markdown.
Do not include code fences.
Do not include any text outside the JSON.

PHP code:
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
                JSON_THROW_ON_ERROR
            );
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'The LLM returned invalid JSON.',
                0,
                $exception
            );
        }

        if (!isset($data['findings']) || !is_array($data['findings'])) {
            throw new \RuntimeException(
                'The LLM JSON response does not contain a valid findings array.'
            );
        }

        $findings = [];

        foreach ($data['findings'] as $finding) {
            if (!is_array($finding)) {
                throw new \RuntimeException(
                    'A review finding must be a JSON object.'
                );
            }

            $requiredFields = [
                'severity',
                'category',
                'message',
                'suggestion',
            ];

            foreach ($requiredFields as $field) {
                if (
                    !array_key_exists($field, $finding)
                    || !is_string($finding[$field])
                ) {
                    throw new \RuntimeException(
                        sprintf(
                            'Review finding field "%s" is missing or invalid.',
                            $field
                        )
                    );
                }
            }

            $findings[] = new ReviewFinding(
                severity: $finding['severity'],
                category: $finding['category'],
                message: $finding['message'],
                suggestion: $finding['suggestion'],
            );
        }

        return new ReviewResult($findings);
    }
}