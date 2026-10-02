<?php

declare(strict_types=1);

namespace App\AI\Review;

use App\AI\DTO\Review\ReviewFindingCollection;

final class ReviewResult
{
    private readonly ReviewFindingCollection $findings;

    /** @param list<ReviewFinding> $findings */
    public function __construct(array $findings)
    {
        $this->findings = new ReviewFindingCollection($findings);
    }

    public function getFindings(): ReviewFindingCollection
    {
        return $this->findings;
    }

    public function count(): int
    {
        return count($this->findings);
    }

    public function hasFindings(): bool
    {
        return !$this->findings->isEmpty();
    }
}
