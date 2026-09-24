<?php

declare(strict_types=1);

namespace App\Tests\AI\Review;

use App\AI\Review\ChangedLinesParser;
use App\AI\Review\ReviewCommentPreparer;
use App\AI\Review\ReviewResultSerializer;
use PHPUnit\Framework\TestCase;

final class ReviewCommentPreparerTest extends TestCase
{
    public function testItSeparatesInlineAndGeneralComments(): void
    {
        $serializer = new ReviewResultSerializer();
        $preparer = new ReviewCommentPreparer(
            $serializer,
            new ChangedLinesParser(),
        );

        $reviewJson = $serializer->serialize(
            [
                'src/Example.php' => new \App\AI\Review\ReviewResult([
                    new \App\AI\Review\ReviewFinding(
                        line: 4,
                        severity: 'high',
                        category: 'bug',
                        message: 'Changed line has a bug.',
                        suggestion: 'Fix the bug.',
                    ),
                    new \App\AI\Review\ReviewFinding(
                        line: 40,
                        severity: 'low',
                        category: 'maintainability',
                        message: 'This line is outside the diff.',
                        suggestion: 'Consider simplifying it.',
                    ),
                ]),
            ],
            'HEAD_SHA',
        );

        $result = $preparer->prepare(
            $reviewJson,
            "+++ b/src/Example.php\n@@ -3,0 +4,1 @@\n+changed\n",
        );

        self::assertCount(1, $result['inline']);
        self::assertCount(1, $result['general']);
        self::assertSame('src/Example.php', $result['inline'][0]['path']);
        self::assertSame(4, $result['inline'][0]['line']);
        self::assertStringContainsString(
            'ai-code-review-fingerprint:',
            $result['inline'][0]['body'],
        );
    }
}
