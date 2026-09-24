<?php

declare(strict_types=1);

namespace App\AI\Review;

final class ReviewArtifactFilter
{
    /**
     * @param array<string, bool> $resolvedFingerprints
     */
    public function filter(
        string $file,
        array $resolvedFingerprints,
    ): int {
        $json = $this->readFile($file);

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'Review result is not valid JSON.',
                0,
                $exception,
            );
        }

        if (!is_array($data) || !isset($data['reviews']) || !is_array($data['reviews'])) {
            throw new \RuntimeException(
                'Review result does not contain a valid reviews object.',
            );
        }

        $removed = 0;

        foreach ($data['reviews'] as $filePath => $review) {
            if (!is_array($review) || !isset($review['findings']) || !is_array($review['findings'])) {
                continue;
            }

            $data['reviews'][$filePath]['findings'] = array_values(array_filter(
                $review['findings'],
                function (mixed $finding) use (
                    $filePath,
                    $resolvedFingerprints,
                    &$removed,
                ): bool {
                    if (!is_array($finding)) {
                        return true;
                    }

                    $fingerprint = $this->fingerprint($filePath, $finding);

                    if (!isset($resolvedFingerprints[$fingerprint])) {
                        return true;
                    }

                    $removed++;

                    return false;
                },
            ));
        }

        try {
            $filteredJson = json_encode(
                $data,
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
            ) . PHP_EOL;
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'Failed to encode filtered review results.',
                0,
                $exception,
            );
        }

        if (file_put_contents($file, $filteredJson) === false) {
            throw new \RuntimeException(
                sprintf('Failed to write filtered review result: %s', $file),
            );
        }

        return $removed;
    }

    /**
     * @return array<string, bool>
     */
    public function readResolvedFingerprints(string $file): array
    {
        $json = $this->readFile($file);

        try {
            $fingerprints = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                'Resolved review fingerprints are not valid JSON.',
                0,
                $exception,
            );
        }

        if (!is_array($fingerprints)) {
            throw new \RuntimeException(
                'Resolved review fingerprints must be a JSON array.',
            );
        }

        $result = [];

        foreach ($fingerprints as $fingerprint) {
            if (is_string($fingerprint) && preg_match('/^[a-f0-9]{64}$/', $fingerprint)) {
                $result[$fingerprint] = true;
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $finding
     */
    private function fingerprint(string $file, array $finding): string
    {
        $payload = implode('|', [
            $this->normalize(ltrim($file, '/')),
            $this->normalize((string) ($finding['category'] ?? '')),
            $this->normalize((string) ($finding['message'] ?? '')),
            $this->normalize((string) ($finding['suggestion'] ?? '')),
        ]);

        return hash('sha256', $payload);
    }

    private function normalize(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', strtolower(trim($value)));

        return $normalized ?? strtolower(trim($value));
    }

    private function readFile(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException(
                sprintf('File cannot be read: %s', $file),
            );
        }

        $content = file_get_contents($file);

        if ($content === false || trim($content) === '') {
            throw new \RuntimeException(
                sprintf('File is empty: %s', $file),
            );
        }

        return $content;
    }
}
