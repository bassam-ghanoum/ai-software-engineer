<?php

declare(strict_types=1);

namespace App\Tests\AI\Git;

use App\AI\Git\GitClient;
use App\AI\Git\GitCommandRunnerInterface;
use PHPUnit\Framework\TestCase;

final class GitClientTest extends TestCase
{
    public function testItReturnsChangedPhpFilesFromGit(): void
    {
        $commandRunner = $this->createMock(
            GitCommandRunnerInterface::class,
        );

        $commandRunner
            ->expects(self::once())
            ->method('run')
            ->with(
                "git diff --name-only -z --diff-filter=ACMR --end-of-options 'HEAD~1' 'HEAD' -- '*.php'",
            )
            ->willReturn([
                'output' => "src/Foo.php\0src/Bar.php\0\0  src/Baz.php  \0src/Multi\nLine.php\0",
                'exitCode' => 0,
            ]);

        $client = new GitClient($commandRunner);

        $result = $client->getChangedPhpFiles(
            'HEAD~1',
            'HEAD',
        );

        self::assertSame(
            [
                'src/Foo.php',
                'src/Bar.php',
                '  src/Baz.php  ',
                "src/Multi\nLine.php",
            ],
            $result,
        );
    }

    public function testItThrowsExceptionWhenGitCommandFails(): void
    {
        $commandRunner = $this->createMock(
            GitCommandRunnerInterface::class,
        );

        $commandRunner
            ->expects(self::once())
            ->method('run')
            ->willReturn([
                'output' => [],
                'exitCode' => 128,
            ]);

        $client = new GitClient($commandRunner);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to determine changed PHP files from Git.',
        );

        $client->getChangedPhpFiles('HEAD~1', 'HEAD');
    }

    public function testItReadsExistingFileContent(): void
    {
        $commandRunner = $this->createStub(
            GitCommandRunnerInterface::class,
        );

        $client = new GitClient($commandRunner);

        $path = 'src/AI/LLM/LlmInterface.php';

        $result = $client->readFile($path);

        self::assertIsString($result);
        self::assertStringContainsString(
            'interface LlmInterface',
            $result,
        );
    }

    public function testItReadsFileContentAtSpecifiedRevision(): void
    {
        $commandRunner = $this->createMock(
            GitCommandRunnerInterface::class,
        );

        $commandRunner
            ->expects(self::once())
            ->method('run')
            ->with("git show --end-of-options 'HEAD:src/Foo.php'")
            ->willReturn([
                'output' => "<?php\nreturn 'target revision';\n",
                'exitCode' => 0,
            ]);

        $client = new GitClient($commandRunner);

        self::assertSame(
            "<?php\nreturn 'target revision';\n",
            $client->readFileAtRevision('src/Foo.php', 'HEAD'),
        );
    }

    public function testItThrowsWhenFileDoesNotExistAtRevision(): void
    {
        $commandRunner = $this->createMock(
            GitCommandRunnerInterface::class,
        );

        $commandRunner
            ->expects(self::once())
            ->method('run')
            ->with("git show --end-of-options 'HEAD:missing.php'")
            ->willReturn([
                'output' => '',
                'exitCode' => 128,
            ]);

        $client = new GitClient($commandRunner);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to read PHP file from Git revision: HEAD:missing.php',
        );

        $client->readFileAtRevision('missing.php', 'HEAD');
    }

    public function testItThrowsExceptionWhenFileDoesNotExist(): void
    {
        $commandRunner = $this->createStub(
            GitCommandRunnerInterface::class,
        );

        $client = new GitClient($commandRunner);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to read PHP file: does-not-exist.php',
        );

        $client->readFile('does-not-exist.php');
    }
}