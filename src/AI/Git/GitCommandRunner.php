<?php

declare(strict_types=1);

namespace App\AI\Git;

final class GitCommandRunner implements GitCommandRunnerInterface
{
    public function run(string $command): array
    {
        $output = [];
        $exitCode = 0;

        exec($command, $output, $exitCode);

        return [
            'output' => $output,
            'exitCode' => $exitCode,
        ];
    }
}