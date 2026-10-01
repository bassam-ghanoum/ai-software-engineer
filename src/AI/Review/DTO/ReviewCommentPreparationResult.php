<?php

declare(strict_types=1);

namespace App\AI\Review\DTO;

final readonly class ReviewCommentPreparationResult
{
    public function __construct(
        public ChangedLines $changedLines,
        public ReviewCommentCollection $inline,
        public ReviewCommentCollection $general,
    ) {
    }
}