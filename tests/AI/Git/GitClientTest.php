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
                [
                    'git',
                    'diff',
                    '--name-only',
                    '-z',
                    '--diff-filter=ACMR',
                    '--end-of-options',
                    'HEAD~1',
                    'HEAD',
                    '--',
                    '*.php',
                ],
            )
            ->willReturn([
                'output' => "src/Foo.php\0src/Bar.php\0\0  src/Baz.php  \0src/Multi\nLine.php\0",
                'errorOutput' => '',
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
                'output' => '',
                'errorOutput' => 'bad revision',
                'exitCode' => 128,
            ]);

        $client = new GitClient($commandRunner);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to determine changed PHP files from Git: bad revision',
        );

        $client->getChangedPhpFiles('HEAD~1', 'HEAD');
    }

    public function testItReadsExistingFileContent(): void
    {
        $commandRunner = $this->createMock(
            GitCommandRunnerInterface::class,
        );
        $commandRunner
            ->expects(self::never())
            ->method('run');

        $client = new GitClient($commandRunner);

        $path = tempnam(sys_get_temp_dir(), 'git-client-');
        self::assertNotFalse($path);

        try {
            $expected = '<?php interface Example {}';
            self::assertSame(strlen($expected), file_put_contents($path, $expected));

            self::assertSame($expected, $client->readFile($path));
        } finally {
            unlink($path);
        }
    }

    public function testItReadsFileContentAtSpecifiedRevision(): void
    {
        $commandRunner = $this->createMock(
            GitCommandRunnerInterface::class,
        );

        $commandRunner
            ->expects(self::once())
            ->method('run')
            ->with(['git', 'show', '--end-of-options', 'HEAD:src/Foo.php'])
            ->willReturn([
                'output' => "<?php\nreturn 'target revision';\n",
                'errorOutput' => '',
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
            ->with(['git', 'show', '--end-of-options', 'HEAD:missing.php'])
            ->willReturn([
                'output' => '',
                'errorOutput' => 'path not found',
                'exitCode' => 128,
            ]);

        $client = new GitClient($commandRunner);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to read PHP file from Git revision HEAD:missing.php: path not found',
        );

        $client->readFileAtRevision('missing.php', 'HEAD');
    }

    public function testItThrowsExceptionWhenFileDoesNotExist(): void
    {
        $commandRunner = $this->createMock(
            GitCommandRunnerInterface::class,
        );
        $commandRunner
            ->expects(self::never())
            ->method('run');

        $client = new GitClient($commandRunner);

        $path = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'missing-'
            . bin2hex(random_bytes(16))
            . '.php';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            sprintf('Failed to read PHP file: %s', $path),
        );

        $client->readFile($path);
    }
}