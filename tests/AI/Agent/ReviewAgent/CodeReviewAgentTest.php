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
                    $normalizedPrompt = str_replace(["\r\n", "\r"], "\n", $prompt);
                    self::assertStringContainsString('File:', $prompt);
                    self::assertStringContainsString('test.php', $prompt);
                    self::assertStringContainsString(
                        "<?php\n\n\n",
                        $normalizedPrompt,
                    );
                    self::assertStringNotContainsString('%%FILE_PATH%%', $prompt);
                    self::assertStringNotContainsString('%%CODE%%', $prompt);
                    self::assertStringContainsString('"source_line"', $prompt);

                    return true;
                },
            ))
            ->willReturn(
                json_encode([
                    'findings' => [
                        [
                            'line' => 5,
                            'source_line' => '$query = $_GET["query"];',
                            'severity' => 'critical',
                            'category' => 'security',
                            'message' => 'SQL injection vulnerability detected.',
                            'suggestion' => 'Use a prepared statement.',
                        ],
                        [
                            'line' => 12,
                            'source_line' => '$result = mysqli_query($connection, $query);',
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
            <<<'PHP'
<?php



$query = $_GET["query"];






$result = mysqli_query($connection, $query);
PHP
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
            'SQL injection vulnerability detected.',
            $result->getFindings()->get(0)->getMessage(),
        );
        self::assertSame(
            'Use a prepared statement.',
            $result->getFindings()->get(0)->getSuggestion(),
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

        self::assertSame(
            'Database errors are not handled.',
            $result->getFindings()->get(1)->getMessage(),
        );
        self::assertSame(
            'Handle query failures explicitly.',
            $result->getFindings()->get(1)->getSuggestion(),
        );
    }

    public function testItRejectsAnEmptyFilePathWithoutCallingTheLlm(): void
    {
        $llm = $this->createMock(LlmInterface::class);
        $llm->expects(self::never())->method('generateJson');

        $agent = $this->createAgent($llm);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The file path cannot be empty.');

        $agent->review('  ', '<?php echo "Hello World";');
    }

    public function testItRejectsEmptySourceWithoutCallingTheLlm(): void
    {
        $llm = $this->createMock(LlmInterface::class);
        $llm->expects(self::never())->method('generateJson');

        $agent = $this->createAgent($llm);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The code cannot be empty.');

        $agent->review('test.php', '');
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

    public function testItRejectsFindingWhenSourceLineIsNotInTheReviewedSource(): void
    {
        $llm = $this->createStub(LlmInterface::class);

        $llm
            ->method('generateJson')
            ->willReturn(
                json_encode([
                    'findings' => [
                        [
                            'line' => 1,
                            'source_line' => 'echo 42;',
                            'severity' => 'low',
                            'category' => 'maintainability',
                            'message' => 'The line does not exist.',
                            'suggestion' => 'Use a valid source line.',
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)
            );

        $agent = $this->createAgent($llm);

        try {
            $agent->review(
                'test.php',
                '<?php echo "Hello World";'
            );
            self::fail('Expected the invalid source line to be rejected.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString(
                'The review finding source_line does not match the reviewed source.',
                $exception->getMessage(),
            );
            self::assertStringContainsString(
                'File: test.php; reported line: 1; reported source_line: \'echo 42;\';',
                $exception->getMessage(),
            );
            self::assertStringContainsString(
                'actual source at reported line: \'<?php echo "Hello World";\'',
                $exception->getMessage(),
            );
            self::assertStringContainsString(
                'nearby source lines: [1: \'<?php echo "Hello World";\']',
                $exception->getMessage(),
            );
        }
    }

    public function testItLocatesSourceLineWhenModelOmitsItsIndentation(): void
    {
        $llm = $this->createStub(LlmInterface::class);
        $llm
            ->method('generateJson')
            ->willReturn(json_encode([
                'findings' => [
                    [
                        'line' => 2,
                        'source_line' => 'return false;',
                        'source_before' => '{',
                        'source_after' => '}',
                        'severity' => 'medium',
                        'category' => 'bug',
                        'message' => 'The return value indicates failure.',
                        'suggestion' => 'Handle the failure result.',
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

        $result = $this->createAgent($llm)->review(
            'test.php',
            "<?php\nfunction example(): bool\n{\n    return false;\n}",
        );

        self::assertSame(
            4,
            $result->getFindings()->get(0)->getLine(),
        );
    }

    public function testItUsesUniqueSourceLineWhenModelReportsIncorrectContext(): void
    {
        $llm = $this->createStub(LlmInterface::class);
        $llm
            ->method('generateJson')
            ->willReturn(json_encode([
                'findings' => [
                    [
                        'line' => 1,
                        'source_line' => 'sleep(1);',
                        'source_before' => 'return;',
                        'source_after' => '}',
                        'severity' => 'medium',
                        'category' => 'performance',
                        'message' => 'The call delays the request.',
                        'suggestion' => 'Avoid blocking sleep in request handling.',
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

        $result = $this->createAgent($llm)->review(
            'test.php',
            "<?php\nfunction example(): void\n{\n    sleep(1);\n}",
        );

        self::assertSame(
            4,
            $result->getFindings()->get(0)->getLine(),
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
                            'line' => 1,
                            'source_line' => '<?php echo "Hello World";',
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
                            'source_line' => '<?php echo "Hello World";',
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
                            'source_line' => '<?php echo "Hello World";',
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
                            'source_line' => '<?php echo "Hello World";',
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

    public function testItAcceptsAnEmptyBatchWithoutCallingTheLlm(): void
    {
        $llm = $this->createMock(LlmInterface::class);
        $llm->expects(self::never())->method('generateJson');

        $results = $this->createAgent($llm)->reviewBatch(new ReviewUnitBatch([]));

        self::assertCount(0, $results);
    }

    public function testItDoesNotRetryWhenTheBatchLlmRequestFails(): void
    {
        $exception = new \RuntimeException('LLM request failed.');
        $llm = $this->createMock(LlmInterface::class);
        $llm
            ->expects(self::once())
            ->method('generateJson')
            ->willThrowException($exception);

        $batch = new ReviewUnitBatch([
            new ReviewUnit('unit-1', 'src/Example.php', 1, '<?php echo 1;'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LLM request failed.');

        $this->createAgent($llm)->reviewBatch($batch);
    }

    public function testItRejectsAnAmbiguousSourceLineWithoutContextWhenTheReportedLineDoesNotMatch(): void
    {
        $llm = $this->createStub(LlmInterface::class);
        $llm
            ->method('generateJson')
            ->willReturn(json_encode([
                'findings' => [
                    [
                        'line' => 1,
                        'source_line' => 'echo 1;',
                        'severity' => 'low',
                        'category' => 'code_smell',
                        'message' => 'The statement is duplicated.',
                        'suggestion' => 'Keep only one statement.',
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'The review finding source_line occurs more than once and cannot be located uniquely.',
        );

        $this->createAgent($llm)->review(
            'test.php',
            "<?php\necho 1;\necho 1;",
        );
    }

    public function testItUsesAdjacentSourceContextToResolveRepeatedLinesInABatch(): void
    {
        $llm = $this->createMock(LlmInterface::class);
        $llm
            ->expects(self::once())
            ->method('generateJson')
            ->willReturn(json_encode([
                'units' => [
                    [
                        'unit_id' => 'unit-1',
                        'findings' => [
                            [
                                'line' => 12,
                                'source_line' => 'echo 1;',
                                'source_before' => 'return;',
                                'source_after' => null,
                                'severity' => 'low',
                                'category' => 'code_smell',
                                'message' => 'The statement is unreachable.',
                                'suggestion' => 'Remove the unreachable statement.',
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

        $batch = new ReviewUnitBatch([
            new ReviewUnit(
                'unit-1',
                'src/Example.php',
                10,
                "<?php\necho 1;\nreturn;\necho 1;",
            ),
        ]);

        $results = $this->createAgent($llm)->reviewBatch($batch);

        self::assertSame(
            13,
            $results->getResult('unit-1')?->getFindings()->get(0)->getLine(),
        );
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
                            'source_line' => 'return false;',
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

    public function testItCorrectsTheReportedLineUsingTheExactSourceLine(): void
    {
        $llm = $this->createMock(LlmInterface::class);
        $llm
            ->expects(self::once())
            ->method('generateJson')
            ->with(self::callback(
                static function (string $prompt): bool {
                    self::assertStringContainsString('"unit_id":"unit-1"', $prompt);
                    self::assertStringContainsString('"start_line":270', $prompt);
                    self::assertStringContainsString('"end_line":274', $prompt);

                    return true;
                },
            ))
            ->willReturn(json_encode([
                'units' => [
                    [
                        'unit_id' => 'unit-1',
                        'findings' => [
                            [
                                'line' => 284,
                                'source_line' => 'echo 66;',
                                'severity' => 'low',
                                'category' => 'code_smell',
                                'message' => 'Unreachable code after return.',
                                'suggestion' => 'Remove the unreachable statement.',
                            ],
                        ],
                    ],
                ],
            ], JSON_THROW_ON_ERROR));

        $agent = $this->createAgent($llm);
        $batch = new ReviewUnitBatch([
            new ReviewUnit(
                'unit-1',
                'src/Example.php',
                270,
                <<<'PHP'
<?php
return;

echo 66;
foreach ($items as $item) {}
PHP,
            ),
        ]);

        $results = $agent->reviewBatch($batch);

        self::assertSame(
            273,
            $results->getResult('unit-1')?->getFindings()->get(0)->getLine(),
        );
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
            new PromptTemplateLoader(dirname(__DIR__, 4) . '/prompts'),
        );
    }
}
