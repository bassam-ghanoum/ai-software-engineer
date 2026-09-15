<?php

declare(strict_types=1);

namespace App\AI\File;

use RuntimeException;

final class LocalSourceFileProvider implements SourceFileProviderInterface
{
    public function read(string $filePath): string
    {
        if (!$this->exists($filePath)) {
            throw new RuntimeException(
                sprintf('Source file does not exist: %s', $filePath)
            );
        }

        $content = file_get_contents($filePath);

        if ($content === false) {
            throw new RuntimeException(
                sprintf('Unable to read source file: %s', $filePath)
            );
        }

        return $content;
    }

    public function write(string $filePath, string $content): void
    {
        $directory = dirname($filePath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(
                sprintf('Unable to create directory: %s', $directory)
            );
        }

        $result = file_put_contents($filePath, $content);

        if ($result === false) {
            throw new RuntimeException(
                sprintf('Unable to write source file: %s', $filePath)
            );
        }
    }

    public function exists(string $filePath): bool
    {
        return is_file($filePath);
    }
}