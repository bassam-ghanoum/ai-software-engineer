<?php

declare(strict_types=1);

namespace App\AI\Git;

use App\AI\Git\DTO\ChangedPhpFilePaths;
use App\AI\Git\DTO\GitCommandResult;

final class GitClient implements GitInterface
{
    public function __construct(
        private readonly GitCommandRunnerInterface $commandRunner,
    ) {
    }

    public function getChangedPhpFiles(
        string $from,
        string $to,
    ): ChangedPhpFilePaths {
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

        if ($result->exitCode !== 0) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to determine changed PHP files from Git: %s',
                    trim($result->errorOutput),
                ),
            );
        }

        return new ChangedPhpFilePaths(array_values(
            array_filter(
                explode("\0", $result->output),
                static fn (string $path): bool => $path !== '',
            ),
        ));
    }

    public function readFile(string $path): string
    {
        $realPath = realpath($path);
        if ($realPath === false || !is_file($realPath)) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to read PHP file: %s',
                    $path,
                ),
            );
        }

        $code = file_get_contents($realPath);

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
        if (
            trim($revision) === ''
            || str_contains($revision, ':')
            || preg_match('/[\x00-\x1F\x7F]/', $revision) === 1
        ) {
            throw new \InvalidArgumentException(
                'Git revisions must be non-empty, contain no control characters, and contain no colon.',
            );
        }

        $this->validateGitPath($path);

        $fileSpec = sprintf('%s:%s', $revision, $path);
        $command = ['git', 'show', '--end-of-options', $fileSpec];

        $result = $this->commandRunner->run($command);

        if ($result->exitCode !== 0) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to read PHP file from Git revision %s: %s',
                    $fileSpec,
                    trim($result->errorOutput),
                ),
            );
        }

        return $result->output;
    }

    private function validateGitPath(string $path): void
    {
        if (
            $path === ''
            || str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('/^[a-zA-Z]:/', $path) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $path) === 1
        ) {
            throw new \InvalidArgumentException('Invalid repository-relative Git path.');
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Invalid repository-relative Git path.');
            }
        }
    }

    public function assertWorkingTreeMatchesRevision(string $revision): void
    {
        $result = $this->commandRunner->run([
            'git',
            'diff',
            '--quiet',
            '--no-ext-diff',
            '--no-textconv',
            '--end-of-options',
            $revision,
            '--',
        ]);

        if ($result->exitCode === 1) {
            throw new \RuntimeException(sprintf(
                'The working tree does not match target revision "%s". Refusing to apply fixes.',
                $revision,
            ));
        }

        if ($result->exitCode !== 0) {
            throw new \RuntimeException(sprintf(
                'Failed to compare the working tree with revision "%s": %s',
                $revision,
                trim($result->errorOutput),
            ));
        }
    }
}
