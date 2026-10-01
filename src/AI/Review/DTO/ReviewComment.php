<?php

declare(strict_types=1);

namespace App\AI\Review\DTO;

use InvalidArgumentException;
use JsonSerializable;

final readonly class ReviewComment implements JsonSerializable
{
    public function __construct(
        public string $fingerprint,
        public string $path,
        public int $line,
        public string $side,
        public string $body,
    ) {
        if (
            preg_match('/^[a-f0-9]{64}$/', $fingerprint) !== 1
            || $path === ''
            || $line < 1
            || !in_array($side, ['LEFT', 'RIGHT'], true)
            || $body === ''
        ) {
            throw new InvalidArgumentException('Review comment fields are invalid.');
        }
    }

    /** @return array{fingerprint: string, path: string, line: int, side: string, body: string} */
    public function jsonSerialize(): array
    {
        return [
            'fingerprint' => $this->fingerprint,
            'path' => $this->path,
            'line' => $this->line,
            'side' => $this->side,
            'body' => $this->body,
        ];
    }
}