<?php

declare(strict_types=1);

namespace App\Tests\AI\Review;

use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
use App\AI\Review\ReviewResultSerializer;
use PHPUnit\Framework\TestCase;

final class ReviewResultSerializerTest extends TestCase
{
    public function testItSerializesReviewResults(): void
    {
        $serializer = new ReviewResultSerializer();

        $reviews = [
            'src/Test.php' => new ReviewResult([
                new ReviewFinding(
                    line: 10,
                    severity: 'critical',
                    category: 'security',
                    message: 'SQL injection vulnerability detected.',
                    suggestion: 'Use a prepared statement.',
                ),
                new ReviewFinding(
                    line: 20,
                    severity: 'medium',
                    category: 'error_handling',
                    message: 'Database errors are not handled.',
                    suggestion: 'Handle query failures explicitly.',
                ),
            ]),
            'src/Other.php' => new ReviewResult([]),
        ];

        $json = $serializer->serialize(
            $reviews,
            'abc123456789',
        );

        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(
            'abc123456789',
            $data['commit_sha'],
        );

        self::assertCount(
            2,
            $data['reviews'],
        );

        self::assertSame(
            10,
            $data['reviews']['src/Test.php']['findings'][0]['line'],
        );

        self::assertSame(
            'critical',
            $data['reviews']['src/Test.php']['findings'][0]['severity'],
        );

        self::assertSame(
            'security',
            $data['reviews']['src/Test.php']['findings'][0]['category'],
        );

        self::assertSame(
            'SQL injection vulnerability detected.',
            $data['reviews']['src/Test.php']['findings'][0]['message'],
        );

        self::assertSame(
            'Use a prepared statement.',
            $data['reviews']['src/Test.php']['findings'][0]['suggestion'],
        );

        self::assertSame(
            [],
            $data['reviews']['src/Other.php']['findings'],
        );
    }

    public function testItDeserializesReviewResults(): void
    {
        $serializer = new ReviewResultSerializer();

        $json = json_encode([
            'commit_sha' => 'abc123456789',
            'reviews' => [
                'src/Test.php' => [
                    'findings' => [
                        [
                            'line' => 15,
                            'severity' => 'high',
                            'category' => 'bug',
                            'message' => 'A bug was found.',
                            'suggestion' => 'Fix the bug.',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = $serializer->deserialize($json);

        self::assertSame(
            'abc123456789',
            $result['commit_sha'],
        );

        self::assertArrayHasKey(
            'src/Test.php',
            $result['reviews'],
        );

        $review = $result['reviews']['src/Test.php'];

        self::assertInstanceOf(
            ReviewResult::class,
            $review,
        );

        self::assertCount(
            1,
            $review->getFindings(),
        );

        $finding = $review->getFindings()[0];

        self::assertInstanceOf(
            ReviewFinding::class,
            $finding,
        );

        self::assertSame(15, $finding->getLine());
        self::assertSame('high', $finding->getSeverity());
        self::assertSame('bug', $finding->getCategory());
        self::assertSame('A bug was found.', $finding->getMessage());
        self::assertSame('Fix the bug.', $finding->getSuggestion());
    }

    public function testSerializeAndDeserializePreserveReviewData(): void
    {
        $serializer = new ReviewResultSerializer();

        $reviews = [
            'fixtures/test1.php' => new ReviewResult([
                new ReviewFinding(
                    line: 4,
                    severity: 'high',
                    category: 'bug',
                    message: 'Return type does not match returned value.',
                    suggestion: 'Return a string or change the declared return type.',
                ),
            ]),
        ];

        $json = $serializer->serialize(
            $reviews,
            'commit-sha-123',
        );

        $result = $serializer->deserialize($json);

        self::assertSame(
            'commit-sha-123',
            $result['commit_sha'],
        );

        $finding = $result['reviews']
            ['fixtures/test1.php']
            ->getFindings()[0];

        self::assertSame(4, $finding->getLine());
        self::assertSame('high', $finding->getSeverity());
        self::assertSame('bug', $finding->getCategory());
        self::assertSame(
            'Return type does not match returned value.',
            $finding->getMessage(),
        );
        self::assertSame(
            'Return a string or change the declared return type.',
            $finding->getSuggestion(),
        );
    }

    public function testItRejectsEmptyCommitShaDuringSerialization(): void
    {
        $serializer = new ReviewResultSerializer();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Commit SHA cannot be empty.'
        );

        $serializer->serialize([], '');
    }

    public function testItRejectsInvalidJsonDuringDeserialization(): void
    {
        $serializer = new ReviewResultSerializer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to decode review results JSON.'
        );

        $serializer->deserialize('not valid json');
    }

    public function testItRejectsMissingCommitSha(): void
    {
        $serializer = new ReviewResultSerializer();

        $json = json_encode([
            'reviews' => [],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Review results JSON does not contain a valid commit_sha.'
        );

        $serializer->deserialize($json);
    }

    public function testItRejectsMissingReviews(): void
    {
        $serializer = new ReviewResultSerializer();

        $json = json_encode([
            'commit_sha' => 'abc123',
        ], JSON_THROW_ON_ERROR);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Review results JSON does not contain a valid reviews object.'
        );

        $serializer->deserialize($json);
    }

    public function testItRejectsInvalidFindingLine(): void
    {
        $serializer = new ReviewResultSerializer();

        $json = json_encode([
            'commit_sha' => 'abc123',
            'reviews' => [
                'src/Test.php' => [
                    'findings' => [
                        [
                            'line' => 0,
                            'severity' => 'high',
                            'category' => 'bug',
                            'message' => 'Something is wrong.',
                            'suggestion' => 'Fix it.',
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Finding for file "src/Test.php" has an invalid line.'
        );

        $serializer->deserialize($json);
    }
}
