<?php

declare(strict_types=1);

namespace App\Tests\AI\Review;

use App\AI\Review\ReviewArtifactValidator;
use PHPUnit\Framework\TestCase;

final class ReviewArtifactValidatorTest extends TestCase
{
    public function testItValidatesTheReviewBindingAndFindings(): void
    {
        $file = $this->writeReviewResult([
            'commit_sha' => 'HEAD_SHA',
            'base_sha' => 'BASE_SHA',
            'reviews' => [
                'src/Example.php' => [
                    'findings' => [
                        [
                            'line' => 4,
                            'severity' => 'high',
                            'category' => 'bug',
                            'message' => 'A bug was found.',
                            'suggestion' => 'Fix the bug.',
                        ],
                    ],
                ],
            ],
        ]);

        try {
            $findingsCount = (new ReviewArtifactValidator(
                new \App\AI\Review\ReviewResultSerializer(),
            ))->validate(
                $file,
                'HEAD_SHA',
                'BASE_SHA',
            );

            self::assertSame(1, $findingsCount);
        } finally {
            unlink($file);
        }
    }

    public function testItRejectsAReviewWithTheWrongHeadSha(): void
    {
        $file = $this->writeReviewResult([
            'commit_sha' => 'OTHER_SHA',
            'base_sha' => 'BASE_SHA',
            'reviews' => [],
        ]);

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage(
                'Review result does not belong to the current PR HEAD.'
            );

            (new ReviewArtifactValidator(
                new \App\AI\Review\ReviewResultSerializer(),
            ))->validate(
                $file,
                'HEAD_SHA',
                'BASE_SHA',
            );
        } finally {
            unlink($file);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeReviewResult(array $data): string
    {
        $file = tempnam(sys_get_temp_dir(), 'review-result-');

        self::assertNotFalse($file);

        file_put_contents($file, json_encode($data, JSON_THROW_ON_ERROR));

        return $file;
    }
}
