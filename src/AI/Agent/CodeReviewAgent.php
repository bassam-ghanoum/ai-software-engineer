<?php

namespace App\AI\Agent;

use App\AI\LLM\LlmInterface;

final class CodeReviewAgent
{
    public function __construct(
        private readonly LlmInterface $llm,
    ) {
    }

    public function review(string $code): string
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

Do not modify the code.

Return a concise review explaining the problems you find.

PHP code:
----------------
$code
----------------
PROMPT;

        return $this->llm->generate($prompt);
    }
}