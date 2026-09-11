<?php

namespace App\AI\Review;

final class ReviewResult
{
    /**
     * @param array<int, ReviewFinding> $findings
     */
    public function __construct(
        private readonly array $findings,
    ) {
        foreach ($findings as $finding) {
            if (!$finding instanceof ReviewFinding) {
                throw new \InvalidArgumentException(
                    'All review findings must be instances of ReviewFinding.'
                );
            }
        }
    }

    /**
     * @return array<int, ReviewFinding>
     */
    public function getFindings(): array
    {
        return $this->findings;
    }

    public function count(): int
    {
        return count($this->findings);
    }

    public function hasFindings(): bool
    {
        return $this->findings !== [];
    }
}
