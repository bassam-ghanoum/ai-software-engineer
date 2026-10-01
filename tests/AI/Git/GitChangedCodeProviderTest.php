<?php

declare(strict_types=1);

namespace App\Tests\AI\Git;

use App\AI\Git\GitChangedCodeProvider;
use App\AI\Git\DTO\ChangedPhpFilePaths;
use App\AI\Git\DTO\ChangedPhpFiles;
use App\AI\Git\GitInterface;
use PHPUnit\Framework\TestCase;

final class GitChangedCodeProviderTest extends TestCase
{
    public function testItReturnsChangedPhpFilesWithTheirContent(): void
    {
        $git = $this->createMock(GitInterface::class);

        $git
            ->expects(self::once())
            ->method('getChangedPhpFiles')
            ->with('HEAD~1', 'HEAD')
            ->willReturn(new ChangedPhpFilePaths([
                'src/Service/UserService.php',
                'src/Repository/UserRepository.php',
            ]));

        $git
            ->expects(self::exactly(2))
            ->method('readFileAtRevision')
            ->willReturnMap([
                [
                    'src/Service/UserService.php',
                    'HEAD',
                    '<?php class UserService {}',
                ],
                [
                    'src/Repository/UserRepository.php',
                    'HEAD',
                    '<?php class UserRepository {}',
                ],
            ]);

        $provider = new GitChangedCodeProvider($git);

        $result = $provider->getChangedPhpFiles('HEAD~1', 'HEAD');

        self::assertSame(
            [
                'src/Service/UserService.php' => '<?php class UserService {}',
                'src/Repository/UserRepository.php' => '<?php class UserRepository {}',
            ],
            iterator_to_array($result),
        );
    }

    public function testItReturnsEmptyArrayWhenThereAreNoChangedPhpFiles(): void
    {
        $git = $this->createMock(GitInterface::class);

        $git
            ->expects(self::once())
            ->method('getChangedPhpFiles')
            ->with('HEAD~1', 'HEAD')
            ->willReturn(new ChangedPhpFilePaths([]));

        $git
            ->expects(self::never())
            ->method('readFileAtRevision');

        $provider = new GitChangedCodeProvider($git);

        $result = $provider->getChangedPhpFiles('HEAD~1', 'HEAD');

        self::assertTrue($result->isEmpty());
    }

    public function testItReadsEveryChangedPhpFile(): void
    {
        $git = $this->createMock(GitInterface::class);

        $git
            ->method('getChangedPhpFiles')
            ->willReturn(new ChangedPhpFilePaths([
                'src/A.php',
                'src/B.php',
                'src/C.php',
            ]));

        $git
            ->expects(self::exactly(3))
            ->method('readFileAtRevision')
            ->willReturnMap([
                ['src/A.php', 'HEAD', '<?php class A {}'],
                ['src/B.php', 'HEAD', '<?php class B {}'],
                ['src/C.php', 'HEAD', '<?php class C {}'],
            ]);

        $provider = new GitChangedCodeProvider($git);

        $result = $provider->getChangedPhpFiles('HEAD~1', 'HEAD');

        self::assertCount(3, $result);

        self::assertSame('<?php class A {}', $result->getContent('src/A.php'));
        self::assertSame('<?php class B {}', $result->getContent('src/B.php'));
        self::assertSame('<?php class C {}', $result->getContent('src/C.php'));
    }

    public function testReadFailureIncludesPathAndRevisionAndPreservesCause(): void
    {
        $git = $this->createMock(GitInterface::class);

        $git
            ->expects(self::once())
            ->method('getChangedPhpFiles')
            ->with('BASE', 'HEAD')
            ->willReturn(new ChangedPhpFilePaths(['src/Missing.php']));

        $git
            ->expects(self::once())
            ->method('readFileAtRevision')
            ->with('src/Missing.php', 'HEAD')
            ->willThrowException(new \RuntimeException('Git object missing.'));

        $provider = new GitChangedCodeProvider($git);

        try {
            $provider->getChangedPhpFiles('BASE', 'HEAD');
            self::fail('Expected the provider to reject the incomplete changed-file set.');
        } catch (\RuntimeException $exception) {
            self::assertSame(
                'Failed to read changed PHP file "src/Missing.php" at revision HEAD.',
                $exception->getMessage(),
            );
            self::assertSame('Git object missing.', $exception->getPrevious()?->getMessage());
        }
    }
}