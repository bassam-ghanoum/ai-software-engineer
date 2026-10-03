<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent\ReviewAgent;

use App\AI\Agent\PromptTemplateLoader;
use App\AI\Agent\ReviewAgent\CodeReviewAgent;
use App\AI\DTO\Agent\ReviewUnit;
use App\AI\DTO\Agent\ReviewUnitBatch;
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
            ->with(self::callback(
                function (string $prompt): bool {
                    self::assertStringContainsString('File:', $prompt);
                    self::assertStringContainsString('test.php', $prompt);
                    self::assertStringContainsString(
                        '<?php' . PHP_EOL . PHP_EOL . PHP_EOL,
                        $prompt,
                    );
                    self::assertStringNotContainsString('%%FILE_PATH%%', $prompt);
                    self::assertStringNotContainsString('%%CODE%%', $prompt);

                    return true;
                },
            ))
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

        $agent = $this->createAgent($llm);

        $result = $agent->review(
            'test.php',
            "<?php\n\n\n\n\necho \"Hello World\";\n\n\n\n\n\n"
        );

        self::assertCount(2, $result->getFindings());

        self::assertSame(
            5,
            $result->getFindings()->get(0)->getLine()
        );

        self::assertSame(
            'critical',
            $result->getFindings()->get(0)->getSeverity()
        );

        self::assertSame(
            'security',
            $result->getFindings()->get(0)->getCategory()
        );

        self::assertSame(
            12,
            $result->getFindings()->get(1)->getLine()
        );

        self::assertSame(
            'medium',
            $result->getFindings()->get(1)->getSeverity()
        );

        self::assertSame(
            'error_handling',
            $result->getFindings()->get(1)->getCategory()
        );
    }

    public function testItRejectsInvalidJson(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn('this is not valid JSON');

        $agent = $this->createAgent($llm);

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

        $agent = $this->createAgent($llm);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The LLM JSON response does not contain a valid findings array.'
        );

        $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );
    }

    public function testItRejectsJsonWithoutAnObjectRoot(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn('null');

        $agent = $this->createAgent($llm);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The LLM JSON response does not contain a valid findings array.'
        );

        $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );
    }

    public function testItRejectsFindingOutsideSourceLineRange(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn(
                json_encode([
                    'findings' => [
                        [
                            'line' => 2,
                            'severity' => 'low',
                            'category' => 'maintainability',
                            'message' => 'The line does not exist.',
                            'suggestion' => 'Use a valid source line.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            );

        $agent = $this->createAgent($llm);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Review finding field "line" is missing or invalid.'
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

        $agent = $this->createAgent($llm);

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

        $agent = $this->createAgent($llm);

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

        $agent = $this->createAgent($llm);

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

        $agent = $this->createAgent($llm);

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
                            'line' => 1,
                            'severity' => 'unknown',
                            'category' => 'security',
                            'message' => 'Something is wrong.',
                            'suggestion' => 'Fix it.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            );

        $agent = $this->createAgent($llm);

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

        $agent = $this->createAgent($llm);

        $result = $agent->review(
            'test.php',
            '<?php echo "Hello World";'
        );

        self::assertFalse($result->hasFindings());
        self::assertSame(0, $result->count());
        self::assertCount(0, $result->getFindings());
    }

    public function testItRetriesBatchUnitsIndividuallyWhenTheLlmReturnsUnknownUnitIds(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects(self::exactly(2))
            ->method('generateJson')
            ->willReturnOnConsecutiveCalls(
                json_encode([
                    'units' => [
                        [
                            'unit_id' => 'unknown-unit',
                            'findings' => [],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                json_encode([
                    'findings' => [
                        [
                            'line' => 3,
                            'severity' => 'medium',
                            'category' => 'bug',
                            'message' => 'The return value can be false.',
                            'suggestion' => 'Handle the false return value.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
            );

        $agent = $this->createAgent($llm);
        $batch = new ReviewUnitBatch([
            new ReviewUnit(
                'unit-8',
                'src/Example.php',
                40,
                "<?php\n\nreturn false;",
            ),
        ]);

        $results = $agent->reviewBatch($batch);

        self::assertCount(1, $results);
        self::assertSame(42, $results->getResult('unit-8')?->getFindings()->get(0)->getLine());
    }

    public function testItRetriesBatchUnitsIndividuallyWhenTheLlmReturnsInvalidJson(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects(self::exactly(2))
            ->method('generateJson')
            ->willReturnOnConsecutiveCalls(
                'not valid JSON',
                json_encode(['findings' => []], JSON_THROW_ON_ERROR),
            );

        $agent = $this->createAgent($llm);
        $batch = new ReviewUnitBatch([
            new ReviewUnit('unit-1', 'src/Example.php', 1, '<?php echo 1;'),
        ]);

        $results = $agent->reviewBatch($batch);

        self::assertCount(1, $results);
        self::assertFalse($results->getResult('unit-1')?->hasFindings());
    }

    public function testItRetriesBatchUnitsIndividuallyWhenFindingStructureIsMalformed(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects(self::exactly(2))
            ->method('generateJson')
            ->willReturnOnConsecutiveCalls(
                json_encode([
                    'units' => [
                        [
                            'unit_id' => 'unit-1',
                            'findings' => [
                                [
                                    'line' => 1,
                                    'severity' => 'low',
                                    'category' => 'bug',
                                    'message' => 'Malformed finding.',
                                ],
                            ],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR),
                json_encode(['findings' => []], JSON_THROW_ON_ERROR),
            );

        $agent = $this->createAgent($llm);
        $batch = new ReviewUnitBatch([
            new ReviewUnit('unit-1', 'src/Example.php', 1, '<?php echo 1;'),
        ]);

        $results = $agent->reviewBatch($batch);

        self::assertCount(1, $results);
        self::assertFalse($results->getResult('unit-1')?->hasFindings());
    }

    private function createAgent(LlmInterface $llm): CodeReviewAgent
    {
        return new CodeReviewAgent(
            $llm,
            new PromptTemplateLoader(dirname(__DIR__, 3) . '/prompts'),
        );
    }
}
