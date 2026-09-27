<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

use App\AI\Agent\PromptTemplateLoader;
use App\AI\LLM\LlmInterface;
use App\AI\Review\ReviewResult;
use RuntimeException;

final class FixAgent implements FixAgentInterface
{
    private const TEMPLATE_NAME = 'fix_agent.txt';

    public function __construct(
        private readonly LlmInterface $llm,
        private readonly PromptTemplateLoader $promptTemplateLoader,
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

        return $this->promptTemplateLoader->render(
            self::TEMPLATE_NAME,
            [
                '%%FILE_PATH%%' => $filePath,
                '%%FORMATTED_FINDINGS%%' => $formattedFindings,
                '%%RETRY_FEEDBACK%%' => $retryFeedback,
                '%%SOURCE_CODE%%' => $sourceCode,
            ],
        );
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
                    'AI-Code-Fix-agent returned empty source for %s.',
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
         * Remove accidental AI-Code-Fix-agent presentation markers.
         */
        $source = preg_replace(
            '/^---\s*AI-Code-Fix-agent generated source.*?---\s*$/mi',
            '',
            $source,
        ) ?? $source;

        $source = preg_replace(
            '/^---\s*End AI-Code-Fix-agent generated source.*?---\s*$/mi',
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
                    'AI-Code-Fix-agent did not return valid PHP source for %s: missing <?php opening tag.',
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
                    'AI-Code-Fix-agent returned source containing unsupported output markers for %s.',
                    $filePath,
                ),
            );
        }

        return rtrim($source) . PHP_EOL;
    }
}