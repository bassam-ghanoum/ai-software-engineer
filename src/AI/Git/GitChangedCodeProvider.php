<?php

declare(strict_types=1);

namespace App\AI\Git;

final class GitChangedCodeProvider implements ChangedCodeProviderInterface
{
    public function __construct(
        private readonly GitInterface $git,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function getChangedPhpFiles(
        string $from,
        string $to
    ): array {
        $paths = $this->git->getChangedPhpFiles($from, $to);

        $files = [];

        foreach ($paths as $path) {
            $files[$path] = $this->git->readFileAtRevision($path, $to);
        }

        return $files;
    }
}
