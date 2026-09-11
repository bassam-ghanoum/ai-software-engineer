<?php

namespace App\AI\Git;

final class GitClient implements GitInterface
{
    public function getChangedPhpFiles(
        string $from,
        string $to
    ): array {
        $command = sprintf(
            'git diff --name-only --diff-filter=ACMR %s %s -- \'*.php\'',
            escapeshellarg($from),
            escapeshellarg($to)
        );

        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException(
                'Failed to determine changed PHP files from Git.'
            );
        }

        return array_values(
            array_filter(
                array_map('trim', $output),
                static fn (string $path): bool => $path !== ''
            )
        );
    }

    public function readFile(string $path): string
{
    if (!is_file($path)) {
        throw new \RuntimeException(
            sprintf(
                'Failed to read PHP file: %s',
                $path
            )
        );
    }

    $code = file_get_contents($path);

    if ($code === false) {
        throw new \RuntimeException(
            sprintf(
                'Failed to read PHP file: %s',
                $path
            )
        );
    }

    return $code;
}
}
