<?php

declare(strict_types=1);

namespace App\AI\File;

use RuntimeException;

final class PhpSourceValidator implements SourceValidatorInterface
{
    public function validate(string $filePath, string $sourceCode): void
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'ai_fix_');

        if ($temporaryFile === false) {
            throw new RuntimeException(
                'Unable to create temporary file for PHP validation.'
            );
        }

        try {
            $result = file_put_contents($temporaryFile, $sourceCode);

            if ($result !== strlen($sourceCode)) {
                throw new RuntimeException(
                    'Unable to write temporary file for PHP validation.'
                );
            }

            $command = sprintf(
                '%s -l %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($temporaryFile),
            );

            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException(sprintf(
                    'Invalid PHP source for %s: %s',
                    $filePath,
                    implode(PHP_EOL, $output),
                ));
            }
        } finally {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
    }
}