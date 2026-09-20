<?php

declare(strict_types=1);

namespace App\Tests\AI\Review;

use App\AI\Review\ReviewFinding;
use PHPUnit\Framework\TestCase;

final class ReviewFindingTest extends TestCase
{
    public function testItCreatesAValidFinding(): void
    {
        $finding = new ReviewFinding(
            line: 10,
            severity: 'critical',
            category: 'security',
            message: 'SQL injection vulnerability detected.',
            suggestion: 'Use a prepared statement.',
        );

        self::assertSame(10, $finding->getLine());
        self::assertSame('critical', $finding->getSeverity());
        self::assertSame('security', $finding->getCategory());
        self::assertSame(
            'SQL injection vulnerability detected.',
            $finding->getMessage(),
        );
        self::assertSame(
            'Use a prepared statement.',
            $finding->getSuggestion(),
        );
    }

    public function testItRejectsInvalidLine(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Review finding line must be greater than zero.'
        );

        new ReviewFinding(
            line: 0,
            severity: 'critical',
            category: 'security',
            message: 'Something is wrong.',
            suggestion: 'Fix it.',
        );
    }

    public function testItRejectsInvalidSeverity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid review finding severity: unknown'
        );

        new ReviewFinding(
            line: 10,
            severity: 'unknown',
            category: 'security',
            message: 'Something is wrong.',
            suggestion: 'Fix it.',
        );
    }

    public function testItRejectsInvalidCategory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid review finding category: unknown'
        );

        new ReviewFinding(
            line: 10,
            severity: 'critical',
            category: 'unknown',
            message: 'Something is wrong.',
            suggestion: 'Fix it.',
        );
    }

    public function testItRejectsEmptyMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Review finding message cannot be empty.'
        );

        new ReviewFinding(
            line: 10,
            severity: 'critical',
            category: 'security',
            message: '   ',
            suggestion: 'Fix it.',
        );
    }

    public function testItRejectsEmptySuggestion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Review finding suggestion cannot be empty.'
        );

        new ReviewFinding(
            line: 10,
            severity: 'critical',
            category: 'security',
            message: 'Something is wrong.',
            suggestion: '   ',
        );
    }
}
