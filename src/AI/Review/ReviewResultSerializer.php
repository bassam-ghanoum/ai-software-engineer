<?php

declare(strict_types=1);

namespace App\AI\Review;

final class ReviewResultSerializer
{
    /**
     * @param array<string, ReviewResult> $reviews
     */
    public function serialize(array $reviews, string $commitSha): string
    {
        if (trim($commitSha) === '') {
            throw new \InvalidArgumentException(
                'Commit SHA cannot be empty.'
            );
        }

        $data = [
            'commit_sha' => $commitSha,
            'reviews' => [],
        ];

        foreach ($reviews as $filePath => $reviewResult) {
            if (!$reviewResult instanceof ReviewResult) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Review for file "%s" must be an instance of ReviewResult.',
                        $filePath,
                    )
                );
            }

            $findings = [];

            foreach ($reviewResult->getFindings() as $finding) {
                $findings[] = [
                    'line' => $finding->getLine(),
                    'severity' => $finding->getSeverity(),
                    'category' => $finding->getCategory(),
                    'message' => $finding->getMessage(),
                    'suggestion' => $finding->getSuggestion(),
                ];
            }

            $data['reviews'][$filePath] = [
                'findings' => $findings,
            ];
        }

        try {
            return json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            );
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'Failed to serialize review results to JSON.',
                0,
                $exception,
            );
        }
    }

    /**
     * @return array{
     *     commit_sha: string,
     *     reviews: array<string, ReviewResult>
     * }
     */
    public function deserialize(string $json): array
    {
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
                    "Failed to decode review results JSON.\nJSON error: %s",
                    $exception->getMessage(),
                ),
                0,
                $exception,
            );
        }

        if (!is_array($data)) {
            throw new \RuntimeException(
                'Review results JSON must contain an object.'
            );
        }

        if (
            !isset($data['commit_sha'])
            || !is_string($data['commit_sha'])
            || trim($data['commit_sha']) === ''
        ) {
            throw new \RuntimeException(
                'Review results JSON does not contain a valid commit_sha.'
            );
        }

        if (
            !isset($data['reviews'])
            || !is_array($data['reviews'])
        ) {
            throw new \RuntimeException(
                'Review results JSON does not contain a valid reviews object.'
            );
        }

        $reviews = [];

        foreach ($data['reviews'] as $filePath => $review) {
            if (!is_string($filePath) || trim($filePath) === '') {
                throw new \RuntimeException(
                    'Review result contains an invalid file path.'
                );
            }

            if (!is_array($review)) {
                throw new \RuntimeException(
                    sprintf(
                        'Review for file "%s" must be an object.',
                        $filePath,
                    )
                );
            }

            if (
                !isset($review['findings'])
                || !is_array($review['findings'])
            ) {
                throw new \RuntimeException(
                    sprintf(
                        'Review for file "%s" does not contain a valid findings array.',
                        $filePath,
                    )
                );
            }

            $findings = [];

            foreach ($review['findings'] as $finding) {
                if (!is_array($finding)) {
                    throw new \RuntimeException(
                        sprintf(
                            'A finding for file "%s" must be an object.',
                            $filePath,
                        )
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
                                'Finding for file "%s" is missing field "%s".',
                                $filePath,
                                $field,
                            )
                        );
                    }
                }

                if (
                    !is_int($finding['line'])
                    || $finding['line'] < 1
                ) {
                    throw new \RuntimeException(
                        sprintf(
                            'Finding for file "%s" has an invalid line.',
                            $filePath,
                        )
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
                                'Finding for file "%s" has an invalid "%s" field.',
                                $filePath,
                                $field,
                            )
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

            $reviews[$filePath] = new ReviewResult($findings);
        }

        return [
            'commit_sha' => $data['commit_sha'],
            'reviews' => $reviews,
        ];
    }
}
