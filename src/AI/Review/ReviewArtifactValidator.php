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
        $result = $this->serializer->deserialize($json);

        if ($result->baseSha === null) {
            throw new \RuntimeException(
                'Review result does not contain a valid base SHA.',
            );
        }

        if ($result->commitSha !== $expectedCommitSha) {
            throw new \RuntimeException(
                'Review result does not belong to the current PR HEAD.',
            );
        }

        if ($result->baseSha !== $expectedBaseSha) {
            throw new \RuntimeException(
                'Review result does not belong to the current PR BASE.',
            );
        }

        $findingsCount = 0;

        foreach ($result->reviews as $review) {
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

}
