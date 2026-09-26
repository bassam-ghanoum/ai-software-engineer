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
        $command = [
            'git',
            'diff',
            '--name-only',
            '-z',
            '--diff-filter=ACMR',
            '--end-of-options',
            $from,
            $to,
            '--',
            '*.php',
        ];

        $result = $this->commandRunner->run($command);

        if ($result['exitCode'] !== 0) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to determine changed PHP files from Git: %s',
                    trim($result['errorOutput']),
                ),
            );
        }

        return array_values(
            array_filter(
                explode("\0", $result['output']),
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

    public function readFileAtRevision(string $path, string $revision): string
    {
        $fileSpec = sprintf('%s:%s', $revision, $path);
        $command = ['git', 'show', '--end-of-options', $fileSpec];

        $result = $this->commandRunner->run($command);

        if ($result['exitCode'] !== 0) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to read PHP file from Git revision %s: %s',
                    $fileSpec,
                    trim($result['errorOutput']),
                ),
            );
        }

        return $result['output'];
    }
}