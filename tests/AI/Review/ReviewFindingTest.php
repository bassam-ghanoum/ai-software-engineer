<?php

namespace App\Tests\AI\Review;

use App\AI\Review\ReviewFinding;
use PHPUnit\Framework\TestCase;

final class ReviewFindingTest extends TestCase
{
    public function testItCreatesAValidFinding(): void
    {
        $finding = new ReviewFinding(
            severity: 'critical',
            category: 'security',
            message: 'SQL injection vulnerability detected.',
            suggestion: 'Use a prepared statement with parameter binding.',
        );

        self::assertSame('critical', $finding->getSeverity());
        self::assertSame('security', $finding->getCategory());
        self::assertSame(
            'SQL injection vulnerability detected.',
            $finding->getMessage()
        );
        self::assertSame(
            'Use a prepared statement with parameter binding.',
            $finding->getSuggestion()
        );
    }

    public function testItRejectsInvalidSeverity(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ReviewFinding(
            severity: 'invalid',
            category: 'security',
            message: 'Test message.',
            suggestion: 'Test suggestion.',
        );
    }

    public function testItRejectsInvalidCategory(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ReviewFinding(
            severity: 'high',
            category: 'invalid',
            message: 'Test message.',
            suggestion: 'Test suggestion.',
        );
    }

    public function testItRejectsEmptyMessage(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ReviewFinding(
            severity: 'high',
            category: 'security',
            message: '',
            suggestion: 'Test suggestion.',
        );
    }

    public function testItRejectsEmptySuggestion(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ReviewFinding(
            severity: 'high',
            category: 'security',
            message: 'Test message.',
            suggestion: '',
        );
    }
}