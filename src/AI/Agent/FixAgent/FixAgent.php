<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

use App\AI\LLM\LlmInterface;
use App\AI\Review\ReviewResult;
use RuntimeException;

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
        ?string $previousFailure = null,
    ): string {
        if (!$reviewResult->hasFindings()) {
            return $sourceCode;
        }

        $prompt = $this->buildPrompt(
            $filePath,
            $sourceCode,
            $reviewResult,
            $previousFailure,
        );

        $generatedSource = $this->llm->generate($prompt);

        return $this->extractSource($generatedSource, $filePath);
    }

    private function buildPrompt(
        string $filePath,
        string $sourceCode,
        ReviewResult $reviewResult,
        ?string $previousFailure,
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

        $retryFeedback = '';

        if ($previousFailure !== null) {
            $retryFeedback = <<<FEEDBACK

IMPORTANT — YOUR PREVIOUS ATTEMPT WAS REJECTED.

The validation failure was:

{$previousFailure}

You MUST correct the rejected issue in this attempt.

Do not merely repeat the previous output.
Make the smallest possible additional change required to satisfy
the approved review findings and the validation failure.

FEEDBACK;
        }

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
{$retryFeedback}

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
     * @param array<int, string> $findings
     */
    private function formatFindings(array $findings): string
    {
        return implode("\n", $findings);
    }

    private function extractSource(
        string $generatedSource,
        string $filePath,
    ): string {
        $source = trim($generatedSource);

        if ($source === '') {
            throw new RuntimeException(
                sprintf(
                    'Agent 2 returned empty source for %s.',
                    $filePath,
                ),
            );
        }

        /*
         * Handle Markdown code fences if the model ignores the instruction.
         */
        if (
            str_starts_with($source, '```php')
            && str_ends_with($source, '```')
        ) {
            $source = trim(substr($source, 6, -3));
        } elseif (
            str_starts_with($source, '```')
            && str_ends_with($source, '```')
        ) {
            $source = trim(substr($source, 3, -3));
        }

        /*
         * Handle the source markers sometimes returned by the model.
         */
        $beginMarker = '---BEGIN SOURCE---';
        $endMarker = '---END SOURCE---';

        $beginPosition = strpos($source, $beginMarker);
        $endPosition = strrpos($source, $endMarker);

        if ($beginPosition !== false) {
            $source = substr(
                $source,
                $beginPosition + strlen($beginMarker),
            );

            $endPosition = strrpos($source, $endMarker);

            if ($endPosition !== false) {
                $source = substr($source, 0, $endPosition);
            }
        }

        /*
         * Remove accidental Agent 2 presentation markers.
         */
        $source = preg_replace(
            '/^---\s*Agent 2 generated source.*?---\s*$/mi',
            '',
            $source,
        ) ?? $source;

        $source = preg_replace(
            '/^---\s*End Agent 2 generated source.*?---\s*$/mi',
            '',
            $source,
        ) ?? $source;

        $source = trim($source);

        /*
         * The PHP source must start at the PHP opening tag.
         */
        $phpPosition = strpos($source, '<?php');

        if ($phpPosition === false) {
            throw new RuntimeException(
                sprintf(
                    'Agent 2 did not return valid PHP source for %s: missing <?php opening tag.',
                    $filePath,
                ),
            );
        }

        if ($phpPosition > 0) {
            $source = substr($source, $phpPosition);
        }

        /*
         * Safety boundary: no model presentation markers may reach
         * PhpSourceValidator.
         */
        if (
            str_contains($source, $beginMarker) ||
            str_contains($source, $endMarker) ||
            str_contains($source, '```')
        ) {
            throw new RuntimeException(
                sprintf(
                    'Agent 2 returned source containing unsupported output markers for %s.',
                    $filePath,
                ),
            );
        }

        return rtrim($source) . PHP_EOL;
    }
}