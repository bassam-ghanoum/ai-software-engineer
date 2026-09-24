<?php

declare(strict_types=1);

namespace App\AI\Review;

final class ReviewArtifactValidator
{
    public function __construct(
        private readonly ReviewResultSerializer $serializer,
    ) {
    }

    public function validate(
        string $file,
        string $expectedCommitSha,
        string $expectedBaseSha,
    ): int {
        if (
            trim($expectedCommitSha) === ''
            || trim($expectedBaseSha) === ''
        ) {
            throw new \InvalidArgumentException(
                'Expected commit SHA and base SHA cannot be empty.',
            );
        }

        $json = $this->readFile($file);
        $data = $this->decodeJson($json);
        $result = $this->serializer->deserialize($json);

        $reviewCommitSha = $data['commit_sha'] ?? null;
        $reviewBaseSha = $data['base_sha'] ?? null;

        if (!is_string($reviewCommitSha) || trim($reviewCommitSha) === '') {
            throw new \RuntimeException(
                'Review result does not contain a valid commit SHA.',
            );
        }

        if (!is_string($reviewBaseSha) || trim($reviewBaseSha) === '') {
            throw new \RuntimeException(
                'Review result does not contain a valid base SHA.',
            );
        }

        if ($reviewCommitSha !== $expectedCommitSha) {
            throw new \RuntimeException(
                'Review result does not belong to the current PR HEAD.',
            );
        }

        if ($reviewBaseSha !== $expectedBaseSha) {
            throw new \RuntimeException(
                'Review result does not belong to the current PR BASE.',
            );
        }

        $findingsCount = 0;

        foreach ($result['reviews'] as $review) {
            $findingsCount += $review->count();
        }

        if ($findingsCount === 0) {
            throw new \RuntimeException(
                'The persisted review contains no findings.',
            );
        }

        return $findingsCount;
    }

    private function readFile(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException(
                sprintf('Review result file cannot be read: %s', $file),
            );
        }

        $content = file_get_contents($file);

        if ($content === false || trim($content) === '') {
            throw new \RuntimeException(
                sprintf('Review result file is empty: %s', $file),
            );
        }

        return $content;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
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
                'Review result is not valid JSON.',
                0,
                $exception,
            );
        }

        if (!is_array($data)) {
            throw new \RuntimeException(
                'Review result must contain a JSON object.',
            );
        }

        return $data;
    }
}
