<?php

namespace App\Tests\AI\Git;

use App\AI\Git\GitClient;
use PHPUnit\Framework\TestCase;

final class GitClientTest extends TestCase
{
    public function testItReturnsChangedPhpFilesFromGit(): void
    {
        $client = new GitClient();

        $result = $client->getChangedPhpFiles('HEAD~1', 'HEAD');

        self::assertIsArray($result);

        foreach ($result as $path) {
            self::assertIsString($path);
            self::assertStringEndsWith('.php', $path);
        }
    }

    public function testItReadsExistingFileContent(): void
    {
        $client = new GitClient();

        $path = 'src/AI/LLM/LlmInterface.php';

        $result = $client->readFile($path);

        self::assertIsString($result);
        self::assertStringContainsString(
            'interface LlmInterface',
            $result
        );
    }

    public function testItThrowsExceptionWhenFileDoesNotExist(): void
    {
        $client = new GitClient();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Failed to read PHP file: does-not-exist.php'
        );

        $client->readFile('does-not-exist.php');
    }
}