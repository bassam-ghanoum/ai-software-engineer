<?php

declare(strict_types=1);

namespace App\AI\Agent;

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

                foreach (['line', 'severity', 'category', 'message', 'suggestion'] as $field) {
                    if (!array_key_exists($field, $finding)) {
                        throw new \RuntimeException(sprintf(
                            'Review finding field "%s" is missing or invalid.',
                            $field,
                        ));
                    }
                }

                if (
                    !is_int($finding['line'])
                    || $finding['line'] < $unit->startLine
                    || $finding['line'] > $unit->endLine()
                ) {
                    throw new \RuntimeException('Review finding line is outside the supplied source range.');
                }

                foreach (['severity', 'category', 'message', 'suggestion'] as $field) {
                    if (!is_string($finding[$field])) {
                        throw new \RuntimeException(sprintf(
                            'Review finding field "%s" is missing or invalid.',
                            $field,
                        ));
                    }
                }

                $findings[] = new ReviewFinding(
                    $finding['line'],
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
