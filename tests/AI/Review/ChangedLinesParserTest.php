<?php

declare(strict_types=1);

namespace App\Tests\AI\Review;

use App\AI\Review\ChangedLinesParser;
use PHPUnit\Framework\TestCase;

final class ChangedLinesParserTest extends TestCase
{
    public function testItParsesChangedLinesByFile(): void
    {
        $parser = new ChangedLinesParser();

        $changedLines = $parser->parse(<<<'DIFF'
diff --git a/src/Example.php b/src/Example.php
--- a/src/Example.php
+++ b/src/Example.php
@@ -2,0 +3,2 @@
+new line
+another line
@@ -10,1 +12,1 @@
-replaced
+replacement
DIFF);

        self::assertSame(
            [
                'src/Example.php' => [
                    3 => true,
                    4 => true,
                    12 => true,
                ],
            ],
            $changedLines,
        );
    }
}
