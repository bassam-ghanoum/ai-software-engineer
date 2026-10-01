<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent\FixAgent;

use App\AI\Agent\FixAgent\FixResult;
use App\AI\Agent\FixAgent\DTO\FixedFiles;
use PHPUnit\Framework\TestCase;

final class FixResultTest extends TestCase
{
    public function testReturnsFixedFiles(): void
    {
        $files = [
            'src/Test.php' => '<?php echo "fixed";',
            'src/Other.php' => '<?php echo "other";',
        ];

        $result = new FixResult(new FixedFiles($files));

        self::assertSame($files, iterator_to_array($result->getFixedFiles()));
    }

    public function testCountReturnsNumberOfFixedFiles(): void
    {
        $result = new FixResult(new FixedFiles([
            'src/Test.php' => '<?php',
            'src/Other.php' => '<?php',
        ]));

        self::assertSame(2, $result->count());
    }

    public function testHasChangesReturnsFalseWhenThereAreNoFiles(): void
    {
        $result = new FixResult(new FixedFiles([]));

        self::assertFalse($result->hasChanges());
    }

    public function testHasChangesReturnsTrueWhenFilesWereFixed(): void
    {
        $result = new FixResult(new FixedFiles([
            'src/Test.php' => '<?php',
        ]));

        self::assertTrue($result->hasChanges());
    }
}