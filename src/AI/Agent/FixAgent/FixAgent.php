<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

use App\AI\LLM\LlmInterface;
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
            $reviewResult,
        );

        $generatedSource = $this->llm->generate($prompt);

        return $this->extractSource($generatedSource);
    }

    private function buildPrompt(
        string $filePath,
        string $sourceCode,
        ReviewResult $reviewResult,
    ): string {
        $findings = [];

        foreach ($reviewResult->getFindings() as $finding) {
            $findings[] = sprintf(
                "- Line %d [%s] [%s]: %s\n  Suggestion: %s",
                $finding->getLine(),
                strtoupper($finding->getSeverity()),
                $finding->getCategory(),
                $finding->getMessage(),
                $finding->getSuggestion(),
            );
        }

        $formattedFindings = $this->formatFindings($findings);

        return <<<PROMPT
You are Agent 2, an AI code-fixing agent.

Your job is to apply ONLY the developer-approved review findings listed below.

You MUST NOT perform a new code review.

You MUST NOT discover, diagnose, or fix any issue that is not explicitly listed
in the approved review findings.

You MUST preserve everything unrelated to the approved findings.

FILE:
{$filePath}

APPROVED REVIEW FINDINGS:
{$formattedFindings}

ORIGINAL SOURCE:
---BEGIN SOURCE---
{$sourceCode}
---END SOURCE---

STRICT EDITING RULES:

1. Apply only the approved findings above.

2. Make the smallest possible change required to fix those findings.

3. Preserve all unrelated source code exactly as provided.

4. Do NOT remove, modify, or rewrite comments unless a comment itself is
   explicitly part of an approved finding.

5. Do NOT add, remove, or move blank lines unless this is strictly required
   for the approved fix.

6. Do NOT reformat the file.

7. Do NOT change indentation or whitespace outside the exact lines that must
   change for the approved fix.

8. Do NOT reorder code.

9. Do NOT rename variables, methods, classes, or other identifiers unless
   explicitly required by an approved finding.

10. Do NOT improve code style.

11. Do NOT make additional security, performance, maintainability, or
    correctness improvements.

12. Do NOT change imports unless explicitly required by an approved finding.

13. Preserve the original comments, blank lines, formatting, and code structure
    wherever they are not directly affected by an approved finding.

14. Return the COMPLETE corrected source code.

15. Do NOT return Markdown code fences.

16. Do NOT return explanations.

17. Do NOT return a diff.

The output must contain only the complete PHP source code.

Before returning the source, compare it mentally with the original source and
ensure that every change is directly required by one of the approved findings.

PROMPT;
    }

    /**
     * Extract only the PHP source from the LLM response.
     */
    private function extractSource(string $generatedSource): string
    {
        $source = trim($generatedSource);

        if ($source === '') {
            throw new \RuntimeException(
                'Agent 2 returned an empty source file.',
            );
        }

        /*
         * Preferred format:
         *
         * ```php
         * <?php
         * ...
         * ```
         */
        if (preg_match(
            '/```(?:php)?\s*(.*?)```/is',
            $source,
            $matches,
        )) {
            $source = trim($matches[1]);
        }

        /*
         * Handle our diagnostic/output wrapper if it is returned by the LLM:
         *
         * --- Agent 2 generated source for fixtures/test1.php ---
         * <?php
         * ...
         * ---END SOURCE---
         */
        $beginMarker = '--- Agent 2 generated source';
        $endMarker = '---END SOURCE---';

        $beginPosition = strpos($source, $beginMarker);

        if ($beginPosition !== false) {
            $afterBegin = strpos($source, "\n", $beginPosition);

            if ($afterBegin === false) {
                throw new \RuntimeException(
                    'Agent 2 returned an invalid source wrapper.',
                );
            }

            $source = substr($source, $afterBegin + 1);

            $endPosition = strpos($source, $endMarker);

            if ($endPosition !== false) {
                $source = substr($source, 0, $endPosition);
            }

            $source = trim($source);
        } else {
            /*
             * Even if the beginning marker is missing, never allow the
             * explicit end marker to reach the PHP validator.
             */
            $endPosition = strpos($source, $endMarker);

            if ($endPosition !== false) {
                $source = substr($source, 0, $endPosition);
                $source = trim($source);
            }
        }

        if ($source === '') {
            throw new \RuntimeException(
                'Agent 2 returned empty PHP source after extraction.',
            );
        }

        /*
         * The prompt requires raw PHP source. If the model nevertheless
         * returned prose before the PHP opening tag, discard that prose.
         */
        $phpPosition = strpos($source, '<?php');

        if ($phpPosition !== false && $phpPosition > 0) {
            $source = substr($source, $phpPosition);
            $source = ltrim($source);
        }

        /*
         * Never allow Markdown fences or our wrapper marker to survive.
         */
        if (str_contains($source, '---END SOURCE---')) {
            throw new \RuntimeException(
                'Agent 2 returned an invalid source containing the END SOURCE marker.',
            );
        }

        if (str_contains($source, '```')) {
            throw new \RuntimeException(
                'Agent 2 returned an invalid source containing Markdown code fences.',
            );
        }

        return $source;
    }

    /**
     * @param array<int, string> $findings
     */
    private function formatFindings(array $findings): string
    {
        return implode("\n", $findings);
    }
}