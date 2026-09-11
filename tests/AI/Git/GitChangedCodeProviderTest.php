<?php

namespace App\Tests\AI\Git;

use App\AI\Git\GitChangedCodeProvider;
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
            ->willReturn([
                'src/Service/UserService.php',
                'src/Repository/UserRepository.php',
            ]);

        $git
            ->expects(self::exactly(2))
            ->method('readFile')
            ->willReturnMap([
                [
                    'src/Service/UserService.php',
                    '<?php class UserService {}',
                ],
                [
                    'src/Repository/UserRepository.php',
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
            $result
        );
    }

    public function testItReturnsEmptyArrayWhenThereAreNoChangedPhpFiles(): void
    {
        $git = $this->createMock(GitInterface::class);

        $git
            ->expects(self::once())
            ->method('getChangedPhpFiles')
            ->with('HEAD~1', 'HEAD')
            ->willReturn([]);

        $git
            ->expects(self::never())
            ->method('readFile');

        $provider = new GitChangedCodeProvider($git);

        $result = $provider->getChangedPhpFiles('HEAD~1', 'HEAD');

        self::assertSame([], $result);
    }

    public function testItReadsEveryChangedPhpFile(): void
    {
        $git = $this->createMock(GitInterface::class);

        $git
            ->method('getChangedPhpFiles')
            ->willReturn([
                'src/A.php',
                'src/B.php',
                'src/C.php',
            ]);

        $git
            ->expects(self::exactly(3))
            ->method('readFile')
            ->willReturnMap([
                ['src/A.php', '<?php class A {}'],
                ['src/B.php', '<?php class B {}'],
                ['src/C.php', '<?php class C {}'],
            ]);

        $provider = new GitChangedCodeProvider($git);

        $result = $provider->getChangedPhpFiles('HEAD~1', 'HEAD');

        self::assertCount(3, $result);

        self::assertSame('<?php class A {}', $result['src/A.php']);
        self::assertSame('<?php class B {}', $result['src/B.php']);
        self::assertSame('<?php class C {}', $result['src/C.php']);
    }
}