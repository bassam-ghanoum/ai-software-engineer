<?php

namespace App\Tests\AI\Review;

use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
use PHPUnit\Framework\TestCase;

final class ReviewResultTest extends TestCase
{
    public function testItStoresReviewFindings(): void
    {
        $finding = new ReviewFinding(
            1,
            severity: 'high',
            category: 'bug',
            message: 'The database connection is not validated.',
            suggestion: 'Validate the database connection before executing queries.',
        );

        $result = new ReviewResult([$finding]);

        self::assertCount(1, $result->getFindings());
        self::assertSame($finding, $result->getFindings()[0]);
        self::assertSame(1, $result->count());
        self::assertTrue($result->hasFindings());
    }

    public function testItAcceptsEmptyFindings(): void
    {
        $result = new ReviewResult([]);

        self::assertCount(0, $result->getFindings());
        self::assertSame(0, $result->count());
        self::assertFalse($result->hasFindings());
    }

    public function testItRejectsInvalidFindingObjects(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'All review findings must be instances of ReviewFinding.'
        );

        new ReviewResult([
            [
                'severity' => 'high',
                'category' => 'bug',
                'message' => 'Invalid finding.',
                'suggestion' => 'Fix it.',
            ],
        ]);
    }
}