<?php

declare(strict_types=1);

namespace App\Tests\AI\Git;

use App\AI\Git\GitCommandRunner;
use PHPUnit\Framework\TestCase;

final class GitCommandRunnerTest extends TestCase
{
    public function testItCapturesRawOutputAndExitCode(): void
    {
        $phpCode = 'fwrite(STDOUT, "first\\0second\\n"); fwrite(STDERR, "diagnostic"); exit(7);';
        $command = sprintf(
            '%s -r %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($phpCode),
        );

        $result = (new GitCommandRunner())->run($command);

        self::assertSame("first\0second\n", $result['output']);
        self::assertSame(7, $result['exitCode']);
    }
}
