<?php

declare(strict_types=1);

namespace App\AI\File;

interface SourceFileProviderInterface
{
    public function read(string $filePath): string;

    public function write(string $filePath, string $content): void;

    public function exists(string $filePath): bool;
}
