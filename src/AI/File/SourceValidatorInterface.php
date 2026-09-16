<?php

declare(strict_types=1);

namespace App\AI\File;

interface SourceValidatorInterface
{
    public function validate(string $filePath, string $sourceCode): void;
}