<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent\FixAgent;

use App\AI\Agent\FixAgent\LlmFixScopeValidator;
use App\AI\LLM\LlmInterface;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LlmFixScopeValidatorTest extends TestCase
{
    public function testApprovedFixDoesNotThrow(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects($this->once())
            ->method('generateJson')
            ->willReturn(json_encode([
                'approved' => true,
                'reason' => 'The fix only addresses the reported issue.',
            ], JSON_THROW_ON_ERROR));

        $validator = new LlmFixScopeValidator($llm);

        $reviewResult = $this->createReviewResult();

        $validator->validate(
            'fixtures/test.php',
            '<?php echo *"Hello";',
            '<?php echo "Hello";',
            $reviewResult,
        );

        $this->addToAssertionCount(1);
    }

    public function testRejectedFixThrowsException(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects($this->once())
            ->method('generateJson')
            ->willReturn(json_encode([
                'approved' => false,
                'reason' => 'The return value was changed without being required.',
            ], JSON_THROW_ON_ERROR));

        $validator = new LlmFixScopeValidator($llm);

        $reviewResult = $this->createReviewResult();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The return value was changed without being required.'
        );

        $validator->validate(
            'fixtures/test.php',
            '<?php echo *"Hello";',
            '<?php echo "Hello"; return "";',
            $reviewResult,
        );
    }

    public function testInvalidJsonThrowsException(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects($this->once())
            ->method('generateJson')
            ->willReturn('not valid json');

        $validator = new LlmFixScopeValidator($llm);

        $reviewResult = $this->createReviewResult();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The fix scope validator returned invalid JSON.'
        );

        $validator->validate(
            'fixtures/test.php',
            '<?php echo *"Hello";',
            '<?php echo "Hello";',
            $reviewResult,
        );
    }

    public function testValidatorSendsFileAndSourceToLlm(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects($this->once())
            ->method('generateJson')
            ->with($this->callback(
                static function (string $prompt): bool {
                    return str_contains($prompt, 'fixtures/test.php')
                        && str_contains($prompt, '<?php echo *"Hello";')
                        && str_contains($prompt, '<?php echo "Hello";')
                        && str_contains($prompt, 'Remove the * operator.');
                }
            ))
            ->willReturn(json_encode([
                'approved' => true,
                'reason' => 'Only the reported syntax issue was fixed.',
            ], JSON_THROW_ON_ERROR));

        $validator = new LlmFixScopeValidator($llm);

        $validator->validate(
            'fixtures/test.php',
            '<?php echo *"Hello";',
            '<?php echo "Hello";',
            $this->createReviewResult(),
        );
    }

    private function createReviewResult(): ReviewResult
    {
        return new ReviewResult([
            new ReviewFinding(
                1,
                'critical',
                'bug',
                'Syntax error caused by the unexpected * operator.',
                'Remove the * operator.',
            ),
        ]);
    }
}