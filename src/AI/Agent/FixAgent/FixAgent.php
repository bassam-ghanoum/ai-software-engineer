<?php

declare(strict_types=1);

namespace App\AI\Agent\FixAgent;

use App\AI\Agent\PromptTemplateLoader;
use App\AI\LLM\LlmInterface;
use App\AI\Review\ReviewResult;
use JsonException;
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

        $generatedEdits = $this->llm->generateJson($prompt);

        return $this->applyEdits(
            $generatedEdits,
            $sourceCode,
            $filePath,
        );
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

    private function applyEdits(
        string $generatedEdits,
        string $sourceCode,
        string $filePath,
    ): string {
        try {
            $data = json_decode(
                $generatedEdits,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException(
                sprintf('AI-Code-Fix-agent returned invalid JSON edits for %s.', $filePath),
                0,
                $exception,
            );
        }

        if (
            !is_array($data)
            || array_keys($data) !== ['edits']
            || !is_array($data['edits'])
            || !array_is_list($data['edits'])
        ) {
            throw new RuntimeException(
                sprintf('AI-Code-Fix-agent returned an invalid edit structure for %s.', $filePath),
            );
        }

        $edits = [];
        $lineEnding = $this->getLineEnding($sourceCode);

        foreach ($data['edits'] as $edit) {
            if (
                !is_array($edit)
                || count($edit) !== 2
                || !array_key_exists('original', $edit)
                || !array_key_exists('replacement', $edit)
                || !is_string($edit['original'])
                || $edit['original'] === ''
                || !is_string($edit['replacement'])
            ) {
                throw new RuntimeException(
                    sprintf('AI-Code-Fix-agent returned an invalid edit for %s.', $filePath),
                );
            }

            $original = $this->normalizeLineEndings(
                $edit['original'],
                $lineEnding,
            );
            $replacement = $this->normalizeLineEndings(
                $edit['replacement'],
                $lineEnding,
            );
            $position = strpos($sourceCode, $original);

            if (
                $position === false
                || strpos(
                    $sourceCode,
                    $original,
                    $position + strlen($original),
                ) !== false
            ) {
                throw new RuntimeException(
                    sprintf(
                        'AI-Code-Fix-agent edit for %s must match exactly one source snippet.',
                        $filePath,
                    ),
                );
            }

            if ($original === $replacement) {
                throw new RuntimeException(
                    sprintf('AI-Code-Fix-agent returned a no-op edit for %s.', $filePath),
                );
            }

            $edits[] = [
                'position' => $position,
                'original' => $original,
                'replacement' => $replacement,
            ];
        }

        usort(
            $edits,
            static fn (array $left, array $right): int => $right['position'] <=> $left['position'],
        );

        $lastStart = strlen($sourceCode);

        foreach ($edits as $edit) {
            $end = $edit['position'] + strlen($edit['original']);

            if ($end > $lastStart) {
                throw new RuntimeException(
                    sprintf('AI-Code-Fix-agent returned overlapping edits for %s.', $filePath),
                );
            }

            $sourceCode = substr_replace(
                $sourceCode,
                $edit['replacement'],
                $edit['position'],
                strlen($edit['original']),
            );
            $lastStart = $edit['position'];
        }

        return $sourceCode;
    }

    private function getLineEnding(string $sourceCode): string
    {
        if (preg_match('/\r\n|\r|\n/', $sourceCode, $matches) === 1) {
            return $matches[0];
        }

        return "\n";
    }

    private function normalizeLineEndings(
        string $source,
        string $lineEnding,
    ): string {
        return preg_replace('/\r\n|\r|\n/', $lineEnding, $source) ?? $source;
    }
}