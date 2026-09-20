<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent;

use App\AI\Agent\CodeReviewAgent;
use App\AI\LLM\LlmInterface;
use PHPUnit\Framework\TestCase;

final class CodeReviewAgentTest extends TestCase
{
    public function testItConvertsLlmJsonIntoReviewResult(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects(self::once())
            ->method('generateJson')
            ->willReturn(
                json_encode([
                    'findings' => [
                        [
                            'line' => 5,
                            'severity' => 'critical',
                            'category' => 'security',
                            'message' => 'SQL injection vulnerability detected.',
                            'suggestion' => 'Use a prepared statement.',
                        ],
                        [
                            'line' => 12,
                            'severity' => 'medium',
                            'category' => 'error_handling',
                            'message' => 'Database errors are not handled.',
                            'suggestion' => 'Handle query failures explicitly.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            );

        $agent = new CodeReviewAgent($llm);

        $result = $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );

        self::assertCount(2, $result->getFindings());

        self::assertSame(
            5,
            $result->getFindings()[0]->getLine()
        );

        self::assertSame(
            'critical',
            $result->getFindings()[0]->getSeverity()
        );

        self::assertSame(
            'security',
            $result->getFindings()[0]->getCategory()
        );

        self::assertSame(
            12,
            $result->getFindings()[1]->getLine()
        );

        self::assertSame(
            'medium',
            $result->getFindings()[1]->getSeverity()
        );

        self::assertSame(
            'error_handling',
            $result->getFindings()[1]->getCategory()
        );
    }

    public function testItRejectsInvalidJson(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn('this is not valid JSON');

        $agent = new CodeReviewAgent($llm);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The LLM returned invalid JSON.'
        );

        $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );
    }

    public function testItRejectsMissingFindingsArray(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn(
                json_encode([
                    'review' => 'No findings',
                ], JSON_THROW_ON_ERROR)
            );

        $agent = new CodeReviewAgent($llm);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The LLM JSON response does not contain a valid findings array.'
        );

        $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );
    }

    public function testItRejectsInvalidFindingStructure(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn(
                json_encode([
                    'findings' => [
                        [
                            'line' => 5,
                            'severity' => 'critical',
                            'category' => 'security',
                            'message' => 'SQL injection.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            );

        $agent = new CodeReviewAgent($llm);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Review finding field "suggestion" is missing or invalid.'
        );

        $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );
    }

    public function testItRejectsMissingLine(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn(
                json_encode([
                    'findings' => [
                        [
                            'severity' => 'critical',
                            'category' => 'security',
                            'message' => 'SQL injection.',
                            'suggestion' => 'Use a prepared statement.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            );

        $agent = new CodeReviewAgent($llm);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Review finding field "line" is missing or invalid.'
        );

        $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );
    }

    public function testItRejectsInvalidLineType(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn(
                json_encode([
                    'findings' => [
                        [
                            'line' => '5',
                            'severity' => 'critical',
                            'category' => 'security',
                            'message' => 'SQL injection.',
                            'suggestion' => 'Use a prepared statement.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            );

        $agent = new CodeReviewAgent($llm);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Review finding field "line" is missing or invalid.'
        );

        $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );
    }

    public function testItRejectsZeroLine(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn(
                json_encode([
                    'findings' => [
                        [
                            'line' => 0,
                            'severity' => 'critical',
                            'category' => 'security',
                            'message' => 'SQL injection.',
                            'suggestion' => 'Use a prepared statement.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            );

        $agent = new CodeReviewAgent($llm);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Review finding field "line" is missing or invalid.'
        );

        $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );
    }

    public function testItRejectsInvalidSeverity(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn(
                json_encode([
                    'findings' => [
                        [
                            'line' => 5,
                            'severity' => 'unknown',
                            'category' => 'security',
                            'message' => 'Something is wrong.',
                            'suggestion' => 'Fix it.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            );

        $agent = new CodeReviewAgent($llm);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Invalid review finding severity: unknown'
        );

        $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );
    }

    public function testItAcceptsEmptyFindings(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn(
                json_encode([
                    'findings' => [],
                ], JSON_THROW_ON_ERROR)
            );

        $agent = new CodeReviewAgent($llm);

        $result = $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );

        self::assertFalse($result->hasFindings());
        self::assertSame(0, $result->count());
        self::assertCount(0, $result->getFindings());
    }
}
