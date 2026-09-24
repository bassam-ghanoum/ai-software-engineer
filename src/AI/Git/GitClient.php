<?php

declare(strict_types=1);

namespace App\AI\Git;

final class GitClient implements GitInterface
{
    public function __construct(
        private readonly GitCommandRunnerInterface $commandRunner,
    ) {
    }

    public function getChangedPhpFiles(
        string $from,
        string $to,
    ): array {
        $command = sprintf(
            'git diff --name-only --diff-filter=ACMR %s %s -- \'*.php\'',
            escapeshellarg($from),
            escapeshellarg($to),
        );

        $result = $this->commandRunner->run($command);

        if ($result['exitCode'] !== 0) {
            throw new \RuntimeException(
                'Failed to determine changed PHP files from Git.',
            );
        }

        return array_values(
            array_filter(
                array_map('trim', $result['output']),
                static fn (string $path): bool => $path !== '',
            ),
        );
    }

    public function readFile(string $path): string
    {
        if (!is_file($path)) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to read PHP file: %s',
                    $path,
                ),
            );
        }

        $code = file_get_contents($path);

        if ($code === false) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to read PHP file: %s',
                    $path,
                ),
            );
        }

        return $code;
    }
}