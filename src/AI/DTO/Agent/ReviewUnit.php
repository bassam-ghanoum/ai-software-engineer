<?php

declare(strict_types=1);

namespace App\AI\DTO\Agent;

use InvalidArgumentException;

final readonly class ReviewUnit
{
    public function __construct(
        public string $id,
        public string $filePath,
        public int $startLine,
        public string $code,
    ) {
        if ($id === '' || $filePath === '' || $startLine < 1 || $code === '') {
            throw new InvalidArgumentException(
                'A review unit must have an ID, path, positive start line, and source code.',
            );
        }
    }

    public function endLine(): int
    {
        return $this->startLine + count(preg_split('/\R/', $this->code)) - 1;
    }
}
