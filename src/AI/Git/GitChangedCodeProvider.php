<?php

declare(strict_types=1);

namespace App\AI\Git;

use RuntimeException;

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
            try {
                $files[$path] = $this->git->readFileAtRevision($path, $to);
            } catch (\Throwable $exception) {
                throw new RuntimeException(
                    sprintf(
                        'Failed to read changed PHP file "%s" at revision %s.',
                        $path,
                        $to,
                    ),
                    0,
                    $exception,
                );
            }
        }

        return $files;
    }
}
