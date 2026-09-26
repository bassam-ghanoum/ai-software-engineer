<?php

declare(strict_types=1);

namespace App\Tests\AI\Review;

use App\AI\Review\ReviewArtifactFilter;
use App\AI\Review\ReviewResultSerializer;
use PHPUnit\Framework\TestCase;

final class ReviewArtifactFilterTest extends TestCase
{
    public function testItRemovesResolvedFindingsAndPreservesUnresolvedFindings(): void
    {
        $reviewFile = tempnam(sys_get_temp_dir(), 'review-result-');
        $fingerprintsFile = tempnam(sys_get_temp_dir(), 'resolved-fingerprints-');

        self::assertNotFalse($reviewFile);
        self::assertNotFalse($fingerprintsFile);

        $resolvedFingerprint = hash(
            'sha256',
            'fixtures/test1.php|bug|test bug.|fix the bug.',
        );

        file_put_contents($reviewFile, json_encode([
            'commit_sha' => 'HEAD',
            'base_sha' => 'BASE',
            'reviews' => [
                'fixtures/test1.php' => [
                    'findings' => [
                        [
                            'line' => 5,
                            'severity' => 'critical',
                            'category' => 'bug',
                            'message' => 'Test bug.',
                            'suggestion' => 'Fix the bug.',
                        ],
                        [
                            'line' => 9,
                            'severity' => 'medium',
                            'category' => 'validation',
                            'message' => 'Another finding.',
                            'suggestion' => 'Fix another finding.',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        file_put_contents(
            $fingerprintsFile,
            json_encode([$resolvedFingerprint], JSON_THROW_ON_ERROR),
        );

        try {
            $filter = new ReviewArtifactFilter(new ReviewResultSerializer());

            self::assertSame(
                1,
                $filter->filter(
                    $reviewFile,
                    $filter->readResolvedFingerprints($fingerprintsFile),
                ),
            );

            $filtered = json_decode(
                (string) file_get_contents($reviewFile),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            self::assertCount(1, $filtered['reviews']['fixtures/test1.php']['findings']);
            self::assertSame(
                'Another finding.',
                $filtered['reviews']['fixtures/test1.php']['findings'][0]['message'],
            );
        } finally {
            unlink($reviewFile);
            unlink($fingerprintsFile);
        }
    }

    public function testItRejectsMalformedReviewFindingsBeforeFiltering(): void
    {
        $reviewFile = tempnam(sys_get_temp_dir(), 'review-result-');

        self::assertNotFalse($reviewFile);

        file_put_contents($reviewFile, json_encode([
            'commit_sha' => 'HEAD',
            'reviews' => [
                'src/Example.php' => [
                    'findings' => [
                        ['line' => 4],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage(
                'Finding for file "src/Example.php" is missing field "severity".',
            );

            (new ReviewArtifactFilter(new ReviewResultSerializer()))->filter(
                $reviewFile,
                [],
            );
        } finally {
            unlink($reviewFile);
        }
    }

    public function testItRejectsObjectInsteadOfResolvedFingerprintArray(): void
    {
        $fingerprintsFile = tempnam(sys_get_temp_dir(), 'resolved-fingerprints-');

        self::assertNotFalse($fingerprintsFile);
        file_put_contents($fingerprintsFile, '{"fingerprint":true}');

        try {
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage(
                'Resolved review fingerprints must be a JSON array.',
            );

            (new ReviewArtifactFilter(new ReviewResultSerializer()))
                ->readResolvedFingerprints($fingerprintsFile);
        } finally {
            unlink($fingerprintsFile);
        }
    }
}
