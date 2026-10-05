<?php

declare(strict_types=1);

namespace App\AI\Agent\ReviewAgent;

use App\AI\Agent\PromptTemplateLoader;
use App\AI\DTO\Agent\ReviewUnit;
use App\AI\DTO\Agent\ReviewUnitBatch;
use App\AI\DTO\Agent\ReviewUnitResults;
use App\AI\LLM\LlmInterface;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;

final class CodeReviewAgent implements CodeReviewAgentInterface
{
    private const TEMPLATE_NAME = 'code_review.txt';

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
            self::TEMPLATE_NAME,
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
                'source_line',
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
            ) {
                throw new \RuntimeException(
                    'Review finding field "line" is missing or invalid.',
                );
            }

            foreach (
                [
                    'source_line',
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

            $sourceContext = $this->getSourceContext($finding);

            $findings[] = new ReviewFinding(
                line: $this->resolveSourceLine(
                    $filePath,
                    $code,
                    $finding['line'],
                    $finding['source_line'],
                    $sourceContext,
                ),
                severity: $finding['severity'],
                category: $finding['category'],
                message: $finding['message'],
                suggestion: $finding['suggestion'],
            );
        }

        return new ReviewResult($findings);
    }

    public function reviewBatch(ReviewUnitBatch $batch): ReviewUnitResults
    {
        if ($batch->isEmpty()) {
            return new ReviewUnitResults([]);
        }

        $payload = [];

        foreach ($batch as $unit) {
            $payload[] = [
                'unit_id' => $unit->id,
                'file_path' => $unit->filePath,
                'start_line' => $unit->startLine,
                'end_line' => $unit->endLine(),
                'source' => $unit->code,
            ];
        }

        $prompt = $this->promptTemplateLoader->render(
            'code_review_batch.txt',
            ['%%UNITS%%' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)],
        );

        // Keep the API request outside the format-recovery catch: transport/API failures
        // should remain visible instead of triggering duplicate LLM calls.
        $response = $this->llm->generateJson($prompt);

        try {
            return $this->parseBatchResponse($response, $batch);
        } catch (\JsonException | \RuntimeException | \InvalidArgumentException) {
            return $this->reviewUnitsIndividually($batch);
        }
    }

    private function parseBatchResponse(
        string $response,
        ReviewUnitBatch $batch,
    ): ReviewUnitResults {
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (
            !is_array($data)
            || !isset($data['units'])
            || !is_array($data['units'])
        ) {
            throw new \RuntimeException(
                'The LLM batch response does not contain a valid units array.',
            );
        }

        $expected = [];

        foreach ($batch as $unit) {
            $expected[$unit->id] = $unit;
        }

        $results = [];

        foreach ($data['units'] as $review) {
            if (!is_array($review) || !is_string($review['unit_id'] ?? null)) {
                throw new \RuntimeException('Each batch review must contain a unit_id.');
            }

            $unitId = $review['unit_id'];
            $unit = $expected[$unitId] ?? null;

            if (!$unit instanceof ReviewUnit || isset($results[$unitId])) {
                throw new \RuntimeException(
                    'The LLM returned an unknown or duplicate review unit ID.',
                );
            }

            if (!isset($review['findings']) || !is_array($review['findings'])) {
                throw new \RuntimeException(sprintf(
                    'The LLM response for "%s" has no valid findings array.',
                    $unitId,
                ));
            }

            $findings = [];

            foreach ($review['findings'] as $finding) {
                if (!is_array($finding)) {
                    throw new \RuntimeException('A review finding must be a JSON object.');
                }

                foreach (
                    ['line', 'severity', 'category', 'message', 'suggestion', 'source_line']
                    as $field
                ) {
                    if (!array_key_exists($field, $finding)) {
                        throw new \RuntimeException(sprintf(
                            'Review finding field "%s" is missing or invalid.',
                            $field,
                        ));
                    }
                }

                if (
                    !is_int($finding['line'])
                    || $finding['line'] < 1
                ) {
                    throw new \RuntimeException('Review finding field "line" is missing or invalid.');
                }

                foreach (['source_line', 'severity', 'category', 'message', 'suggestion'] as $field) {
                    if (!is_string($finding[$field])) {
                        throw new \RuntimeException(sprintf(
                            'Review finding field "%s" is missing or invalid.',
                            $field,
                        ));
                    }
                }

                $sourceContext = $this->getSourceContext($finding);
                $localLine = $this->resolveSourceLine(
                    $unit->filePath,
                    $unit->code,
                    $finding['line'] - $unit->startLine + 1,
                    $finding['source_line'],
                    $sourceContext,
                );
                $absoluteLine = $unit->startLine + $localLine - 1;

                if ($absoluteLine > $unit->endLine()) {
                    throw new \RuntimeException('Review finding line is outside the supplied source range.');
                }

                $findings[] = new ReviewFinding(
                    $absoluteLine,
                    $finding['severity'],
                    $finding['category'],
                    $finding['message'],
                    $finding['suggestion'],
                );
            }

            $results[$unitId] = new ReviewResult($findings);
        }

        if (count($results) !== count($expected)) {
            throw new \RuntimeException(
                'The LLM omitted one or more review units from the batch response.',
            );
        }

        return new ReviewUnitResults($results);
    }

    private function resolveSourceLine(
        string $filePath,
        string $code,
        int $reportedLine,
        string $sourceLine,
        ?array $sourceContext = null,
    ): int {
        $sourceLines = preg_split('/\R/', $code);

        if ($sourceLines === false) {
            throw new \RuntimeException('Unable to split reviewed source into lines.');
        }

        if (
            isset($sourceLines[$reportedLine - 1])
            && $this->sourceLinesMatch(
                $sourceLines[$reportedLine - 1],
                $sourceLine,
            )
        ) {
            return $reportedLine;
        }

        $matchingLines = [];

        foreach ($sourceLines as $index => $line) {
            if ($this->sourceLinesMatch($line, $sourceLine)) {
                $matchingLines[] = $index + 1;
            }
        }

        if (count($matchingLines) === 1) {
            return $matchingLines[0];
        }

        if ($matchingLines === []) {
            throw new \RuntimeException(
                $this->sourceLineMismatchMessage(
                    $filePath,
                    $reportedLine,
                    $sourceLines,
                ),
            );
        }

        if ($sourceContext !== null) {
            $contextMatchingLines = [];

            foreach ($matchingLines as $lineNumber) {
                $index = $lineNumber - 1;
                $beforeMatches = $sourceContext['before'] === null
                    ? $index === 0
                    : (
                        isset($sourceLines[$index - 1])
                        && $this->sourceLinesMatch(
                            $sourceLines[$index - 1],
                            $sourceContext['before'],
                        )
                    );
                $afterMatches = $sourceContext['after'] === null
                    ? $index === count($sourceLines) - 1
                    : (
                        isset($sourceLines[$index + 1])
                        && $this->sourceLinesMatch(
                            $sourceLines[$index + 1],
                            $sourceContext['after'],
                        )
                    );

                if ($beforeMatches && $afterMatches) {
                    $contextMatchingLines[] = $lineNumber;
                }
            }

            if (count($contextMatchingLines) === 1) {
                return $contextMatchingLines[0];
            }
        }

        throw new \RuntimeException(
            'The review finding source_line occurs more than once and cannot be located uniquely.',
        );
    }

    private function sourceLineMismatchMessage(
        string $filePath,
        int $reportedLine,
        array $sourceLines,
    ): string {
        return sprintf(
            'The review finding source_line does not match the reviewed source. '
            . 'File: %s; reported line: %d; line status: %s; source line count: %d.',
            $filePath,
            $reportedLine,
            isset($sourceLines[$reportedLine - 1]) ? 'in range' : 'out of range',
            count($sourceLines),
        );
    }

    private function sourceLinesMatch(
        string $sourceLine,
        string $reportedSourceLine,
    ): bool {
        return $sourceLine === $reportedSourceLine
            || trim($sourceLine) === trim($reportedSourceLine);
    }

    /**
     * @param array<string, mixed> $finding
     * @return array{before: ?string, after: ?string}|null
     */
    private function getSourceContext(array $finding): ?array
    {
        $hasBefore = array_key_exists('source_before', $finding);
        $hasAfter = array_key_exists('source_after', $finding);

        if (!$hasBefore && !$hasAfter) {
            return null;
        }

        if (
            !$hasBefore
            || !$hasAfter
            || (!is_string($finding['source_before']) && $finding['source_before'] !== null)
            || (!is_string($finding['source_after']) && $finding['source_after'] !== null)
        ) {
            throw new \RuntimeException(
                'Review finding source context is missing or invalid.',
            );
        }

        return [
            'before' => $finding['source_before'],
            'after' => $finding['source_after'],
        ];
    }

    private function reviewUnitsIndividually(ReviewUnitBatch $batch): ReviewUnitResults
    {
        $results = [];

        foreach ($batch as $unit) {
            $result = $this->review($unit->filePath, $unit->code);

            if ($unit->startLine > 1) {
                $findings = [];

                foreach ($result->getFindings() as $finding) {
                    $findings[] = new ReviewFinding(
                        $finding->getLine() + $unit->startLine - 1,
                        $finding->getSeverity(),
                        $finding->getCategory(),
                        $finding->getMessage(),
                        $finding->getSuggestion(),
                    );
                }

                $result = new ReviewResult($findings);
            }

            $results[$unit->id] = $result;
        }

        return new ReviewUnitResults($results);
    }
}
