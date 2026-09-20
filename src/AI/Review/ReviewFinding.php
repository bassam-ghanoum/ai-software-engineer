<?php

declare(strict_types=1);

namespace App\AI\Review;

final class ReviewFinding
{
    private const ALLOWED_SEVERITIES = [
        'critical',
        'high',
        'medium',
        'low',
    ];

    private const ALLOWED_CATEGORIES = [
        'security',
        'bug',
        'performance',
        'maintainability',
        'validation',
        'error_handling',
        'code_smell',
    ];

    public function __construct(
        private readonly int $line,
        private readonly string $severity,
        private readonly string $category,
        private readonly string $message,
        private readonly string $suggestion,
    ) {
        if ($line < 1) {
            throw new \InvalidArgumentException(
                'Review finding line must be greater than zero.'
            );
        }

        if (!in_array($severity, self::ALLOWED_SEVERITIES, true)) {
            throw new \InvalidArgumentException(
                'Invalid review finding severity: ' . $severity
            );
        }

        if (!in_array($category, self::ALLOWED_CATEGORIES, true)) {
            throw new \InvalidArgumentException(
                'Invalid review finding category: ' . $category
            );
        }

        if (trim($message) === '') {
            throw new \InvalidArgumentException(
                'Review finding message cannot be empty.'
            );
        }

        if (trim($suggestion) === '') {
            throw new \InvalidArgumentException(
                'Review finding suggestion cannot be empty.'
            );
        }
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSuggestion(): string
    {
        return $this->suggestion;
    }
}
