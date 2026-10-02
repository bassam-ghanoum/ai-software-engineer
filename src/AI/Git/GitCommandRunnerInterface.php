<?php

declare(strict_types=1);

namespace App\AI\Git;

use App\AI\DTO\Git\GitCommandResult;

interface GitCommandRunnerInterface
{
    /**
     * @param list<string> $command
     */
    public function run(array $command): GitCommandResult;
}