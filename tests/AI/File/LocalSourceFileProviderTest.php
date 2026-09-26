<?php

declare(strict_types=1);

namespace App\Tests\AI\File;

use App\AI\File\LocalSourceFileProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LocalSourceFileProviderTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $directory = sys_get_temp_dir() . '/ai-software-engineer-' . uniqid('', true);

        if (!mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(
                sprintf('Unable to create temporary directory: %s', $directory)
            );
        }

        $this->temporaryDirectory = $directory;
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function testReadReturnsFileContent(): void
    {
        $provider = new LocalSourceFileProvider();

        $filePath = $this->temporaryDirectory . '/test.php';

        file_put_contents(
            $filePath,
            '<?php echo "Hello";'
        );

        self::assertSame(
            '<?php echo "Hello";',
            $provider->read($filePath)
        );
    }

    public function testWriteWritesFileContent(): void
    {
        $provider = new LocalSourceFileProvider();

        $filePath = $this->temporaryDirectory . '/test.php';

        $provider->write(
            $filePath,
            '<?php echo "Fixed";'
        );

        self::assertFileExists($filePath);
        self::assertSame(
            '<?php echo "Fixed";',
            file_get_contents($filePath)
        );
    }

    public function testWriteReplacesExistingFileContent(): void
    {
        $provider = new LocalSourceFileProvider();

        $filePath = $this->temporaryDirectory . '/test.php';
        file_put_contents($filePath, '<?php echo "Original";');

        $provider->write($filePath, '<?php echo "Fixed";');

        self::assertSame(
            '<?php echo "Fixed";',
            file_get_contents($filePath),
        );
    }

    public function testWriteCreatesMissingParentDirectories(): void
    {
        $provider = new LocalSourceFileProvider();
        $filePath = $this->temporaryDirectory . '/nested/test.php';

        $provider->write($filePath, '<?php echo "Fixed";');

        self::assertSame(
            '<?php echo "Fixed";',
            file_get_contents($filePath),
        );
    }

    public function testWriteUpdatesTargetOfExistingSymlink(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Symlink behavior is tested on Unix systems.');
        }

        $provider = new LocalSourceFileProvider();
        $targetPath = $this->temporaryDirectory . '/target.php';
        $linkPath = $this->temporaryDirectory . '/link.php';
        file_put_contents($targetPath, '<?php echo "Original";');

        if (!symlink($targetPath, $linkPath)) {
            self::markTestSkipped('Unable to create a test symlink.');
        }

        $provider->write($linkPath, '<?php echo "Fixed";');

        self::assertTrue(is_link($linkPath));
        self::assertSame(
            '<?php echo "Fixed";',
            file_get_contents($targetPath),
        );
    }

    public function testExistsReturnsTrueForExistingFile(): void
    {
        $provider = new LocalSourceFileProvider();

        $filePath = $this->temporaryDirectory . '/test.php';

        file_put_contents($filePath, '<?php');

        self::assertTrue(
            $provider->exists($filePath)
        );
    }

    public function testExistsReturnsFalseForMissingFile(): void
    {
        $provider = new LocalSourceFileProvider();

        $filePath = $this->temporaryDirectory . '/missing.php';

        self::assertFalse(
            $provider->exists($filePath)
        );
    }

    public function testReadThrowsExceptionForMissingFile(): void
    {
        $provider = new LocalSourceFileProvider();

        $this->expectException(RuntimeException::class);

        $provider->read(
            $this->temporaryDirectory . '/missing.php'
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . '/' . $item;

            if (is_dir($path)) {
                $this->removeDirectory($path);
                continue;
            }

            unlink($path);
        }

        rmdir($directory);
    }
}
