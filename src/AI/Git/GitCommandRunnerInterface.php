<?php

declare(strict_types=1);

namespace App\AI\Git;

interface GitCommandRunnerInterface
{
    /**
    * @return array{output: string, exitCode: int}
     */
    public function run(string $command): array;
}