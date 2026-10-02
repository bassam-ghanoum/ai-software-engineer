<?php

declare(strict_types=1);

namespace App\AI\DTO\Review;

final readonly class ReviewCommentPreparationResult
{
    public function __construct(
        public ChangedLines $changedLines,
        public ReviewCommentCollection $inline,
        public ReviewCommentCollection $general,
    ) {
    }
}