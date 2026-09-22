<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent\FixAgent;

use App\AI\Agent\FixAgent\FixAgent;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
use App\AI\LLM\LlmInterface;
use PHPUnit\Framework\TestCase;

final class FixAgentTest extends TestCase
{
    public function testReturnsOriginalSourceWhenThereAreNoFindings(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects(self::never())
            ->method('generate');

        $agent = new FixAgent($llm);

        $sourceCode = <<<'PHP'
<?php

echo "Hello";
PHP;

        $result = $agent->fix(
            'test.php',
            $sourceCode,
            new ReviewResult([]),
        );

        self::assertSame(
            $sourceCode,
            $result,
        );
    }

    public function testGeneratesFixedSourceFromReviewFindings(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects(self::once())
            ->method('generate')
            ->with(self::callback(
                function (string $prompt): bool {
                    self::assertStringContainsString(
                        'You are Agent 2, an AI code-fixing agent.',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'You MUST NOT perform a new code review.',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'You MUST NOT discover, diagnose, or fix any issue that is not explicitly listed',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Make the smallest possible change required to fix those findings.',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Do NOT remove, modify, or rewrite comments',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Do NOT add, remove, or move blank lines',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Do NOT reformat the file.',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Do NOT return Markdown code fences.',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Do NOT return explanations.',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Do NOT return a diff.',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'test.php',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Line 1 [HIGH] [security]: SQL injection',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Suggestion: Use parameterized queries',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        '<?php echo "Hello";',
                        $prompt,
                    );

                    return true;
                },
            ))
            ->willReturn(
                '<?php echo "Fixed";'
            );

        $agent = new FixAgent($llm);

        $result = $agent->fix(
            'test.php',
            '<?php echo "Hello";',
            new ReviewResult([
                new ReviewFinding(
                    1,
                    'high',
                    'security',
                    'SQL injection',
                    'Use parameterized queries',
                ),
            ]),
        );

        self::assertSame(
            '<?php echo "Fixed";' . PHP_EOL,
            $result,
        );
    }

    public function testPassesAllReviewFindingsToLlm(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects(self::once())
            ->method('generate')
            ->with(self::callback(
                function (string $prompt): bool {
                    self::assertStringContainsString(
                        'Line 10 [CRITICAL] [bug]: First issue',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Suggestion: Fix the first issue',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Line 20 [MEDIUM] [maintainability]: Second issue',
                        $prompt,
                    );

                    self::assertStringContainsString(
                        'Suggestion: Fix the second issue',
                        $prompt,
                    );

                    return true;
                },
            ))
            ->willReturn(
                '<?php echo "Fixed";'
            );

        $agent = new FixAgent($llm);

        $agent->fix(
            'test.php',
            '<?php echo "Original";',
            new ReviewResult([
                new ReviewFinding(
                    10,
                    'critical',
                    'bug',
                    'First issue',
                    'Fix the first issue',
                ),
                new ReviewFinding(
                    20,
                    'medium',
                    'maintainability',
                    'Second issue',
                    'Fix the second issue',
                ),
            ]),
        );
    }
public function testReturnsExactlyTheSourceReturnedByLlm(): void
{
    $llm = $this->createMock(LlmInterface::class);

    $fixedSource = <<<'PHP'
<?php

function example(): string
{
    return "Fixed";
}
PHP;

    $llm
        ->expects(self::once())
        ->method('generate')
        ->willReturn($fixedSource);

    $agent = new FixAgent($llm);

    $result = $agent->fix(
        'test.php',
        '<?php echo "Original";',
        new ReviewResult([
            new ReviewFinding(
                1,
                'high',
                'bug',
                'Example issue',
                'Fix the issue',
            ),
        ]),
    );

    self::assertSame(
        $fixedSource . PHP_EOL,
        $result,
    );
}

}
