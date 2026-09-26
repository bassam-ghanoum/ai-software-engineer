<?php

declare(strict_types=1);

namespace App\AI\Git;

interface GitCommandRunnerInterface
{
    /**
     * @param list<string> $command
     * @return array{output: string, errorOutput: string, exitCode: int}
     */
    public function run(array $command): array;
}