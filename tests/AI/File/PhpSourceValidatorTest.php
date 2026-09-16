<?php

declare(strict_types=1);

namespace App\Tests\AI\File;

use App\AI\File\PhpSourceValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PhpSourceValidatorTest extends TestCase
{
    public function testValidPhpSourcePassesValidation(): void
    {
        $validator = new PhpSourceValidator();

        $source = <<<'PHP'
<?php

function hello(): string
{
    return 'Hello';
}
PHP;

        $validator->validate(
            'src/Test.php',
            $source,
        );

        self::assertTrue(true);
    }

    public function testInvalidPhpSourceThrowsException(): void
    {
        $validator = new PhpSourceValidator();

        $source = <<<'PHP'
<?php

function hello(): string
{
    echo *"Hello";
}
PHP;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid PHP source for src/Test.php');

        $validator->validate(
            'src/Test.php',
            $source,
        );
    }
}
