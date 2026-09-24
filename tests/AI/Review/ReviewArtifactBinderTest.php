<?php

declare(strict_types=1);

namespace App\Tests\AI\Review;

use App\AI\Review\ReviewArtifactBinder;
use PHPUnit\Framework\TestCase;

final class ReviewArtifactBinderTest extends TestCase
{
    public function testItBindsCommitAndBaseAndCountsFindings(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'review-result-');

        self::assertNotFalse($file);

        file_put_contents($file, json_encode([
            'commit_sha' => 'UNBOUND',
            'reviews' => [
                'src/Example.php' => [
                    'findings' => [
                        ['line' => 4],
                        ['line' => 8],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        try {
            $findingsCount = (new ReviewArtifactBinder())->bind(
                $file,
                'HEAD_SHA',
                'BASE_SHA',
            );

            self::assertSame(2, $findingsCount);

            $data = json_decode(
                (string) file_get_contents($file),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            self::assertSame('HEAD_SHA', $data['commit_sha']);
            self::assertSame('BASE_SHA', $data['base_sha']);
        } finally {
            unlink($file);
        }
    }
}
