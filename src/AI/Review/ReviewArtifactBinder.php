<?php

declare(strict_types=1);

namespace App\AI\Review;

final class ReviewArtifactBinder
{
    public function bind(
        string $file,
        string $commitSha,
        string $baseSha,
    ): int {
        if (trim($commitSha) === '' || trim($baseSha) === '') {
            throw new \InvalidArgumentException(
                'Commit SHA and base SHA cannot be empty.',
            );
        }

        $data = $this->readJson($file);

        foreach (['commit_sha', 'reviews'] as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \RuntimeException(
                    sprintf('Review result is missing %s.', $field),
                );
            }
        }

        if (!is_array($data['reviews'])) {
            throw new \RuntimeException(
                'Review result contains an invalid reviews structure.',
            );
        }

        $findingsCount = 0;

        foreach ($data['reviews'] as $review) {
            if (!is_array($review) || !isset($review['findings'])) {
                throw new \RuntimeException(
                    'Review result contains an invalid review entry.',
                );
            }

            if (!is_array($review['findings'])) {
                throw new \RuntimeException(
                    'Review result contains an invalid findings structure.',
                );
            }

            $findingsCount += count($review['findings']);
        }

        $data['commit_sha'] = $commitSha;
        $data['base_sha'] = $baseSha;

        $this->writeJson($file, $data);

        return $findingsCount;
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $file): array
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException(
                sprintf('Review result file cannot be read: %s', $file),
            );
        }

        $content = file_get_contents($file);

        if ($content === false) {
            throw new \RuntimeException(
                sprintf('Failed to read review result file: %s', $file),
            );
        }

        try {
            $data = json_decode(
                $content,
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

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $file, array $data): void
    {
        try {
            $content = json_encode(
                $data,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR,
            ) . PHP_EOL;
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'Failed to encode the review result.',
                0,
                $exception,
            );
        }

        if (file_put_contents($file, $content) === false) {
            throw new \RuntimeException(
                sprintf('Failed to write review result file: %s', $file),
            );
        }
    }
}
