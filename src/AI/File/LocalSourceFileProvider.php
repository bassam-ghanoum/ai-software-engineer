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
        if (is_link($filePath)) {
            $resolvedPath = realpath($filePath);

            if ($resolvedPath === false) {
                throw new RuntimeException(
                    sprintf('Unable to resolve source file symlink: %s', $filePath)
                );
            }

            $filePath = $resolvedPath;
        }

        $directory = dirname($filePath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(
                sprintf('Unable to create directory: %s', $directory)
            );
        }

        $temporaryFile = tempnam($directory, '.ai-fix-');

        if ($temporaryFile === false) {
            throw new RuntimeException(
                sprintf('Unable to create temporary source file in: %s', $directory)
            );
        }

        if (realpath(dirname($temporaryFile)) !== realpath($directory)) {
            unlink($temporaryFile);

            throw new RuntimeException(
                sprintf('Unable to create temporary source file in: %s', $directory)
            );
        }

        try {
            $bytesWritten = file_put_contents($temporaryFile, $content);

            if ($bytesWritten !== strlen($content)) {
                throw new RuntimeException(
                    sprintf('Unable to write source file: %s', $filePath)
                );
            }

            $permissions = is_file($filePath)
                ? fileperms($filePath)
                : false;
            $permissions = $permissions === false
                ? 0666 & ~umask()
                : $permissions & 0777;

            if (!chmod($temporaryFile, $permissions)) {
                throw new RuntimeException(
                    sprintf('Unable to set source file permissions: %s', $filePath)
                );
            }

            if (!rename($temporaryFile, $filePath)) {
                throw new RuntimeException(
                    sprintf('Unable to write source file: %s', $filePath)
                );
            }
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }

    public function exists(string $filePath): bool
    {
        return is_file($filePath);
    }
}