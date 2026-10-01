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
        $command = [PHP_BINARY, '-r', $phpCode];

        $result = (new GitCommandRunner())->run($command);

        self::assertSame("first\0second\n", $result->output);
        self::assertSame('diagnostic', $result->errorOutput);
        self::assertSame(7, $result->exitCode);
    }

    public function testCommandArgumentsAreNotInterpretedByAShell(): void
    {
        $argument = 'literal; echo injected';
        $command = [
            PHP_BINARY,
            '-r',
            'echo $argv[1];',
            $argument,
        ];

        $result = (new GitCommandRunner())->run($command);

        self::assertSame($argument, $result->output);
        self::assertSame('', $result->errorOutput);
        self::assertSame(0, $result->exitCode);
    }
}
