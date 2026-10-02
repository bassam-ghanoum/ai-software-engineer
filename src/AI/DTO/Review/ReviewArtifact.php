<?php

declare(strict_types=1);

namespace App\AI\DTO\Review;

use InvalidArgumentException;

final readonly class ReviewArtifact
{
    public function __construct(
        public string $commitSha,
        public ReviewBatch $reviews,
        public ?string $baseSha = null,
    ) {
        if (trim($commitSha) === '') {
            throw new InvalidArgumentException('Commit SHA cannot be empty.');
        }

        if ($baseSha !== null && trim($baseSha) === '') {
            throw new InvalidArgumentException('Base SHA cannot be empty.');
        }
    }
}