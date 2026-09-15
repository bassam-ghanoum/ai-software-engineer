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

    /**
     * @return void
     */
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