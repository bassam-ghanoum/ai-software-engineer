<?php

declare(strict_types=1);

namespace App\Tests\AI\Agent\FixAgent;

use App\AI\Agent\FixAgent\FixAgent;
use App\AI\LLM\LlmInterface;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
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

        $source = '<?php echo "Hello";';

        $result = $agent->fix(
            'test.php',
            $source,
            new ReviewResult([]),
        );

        self::assertSame($source, $result);
    }

    public function testGeneratesFixedSourceFromReviewFindings(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $fixedSource = <<<'PHP'
<?php

function test(): array
{
    echo "Hello, World!";

    return [];
}
PHP;

        $llm
            ->expects(self::once())
            ->method('generate')
            ->with(self::callback(
                static function (string $prompt): bool {
                    return str_contains($prompt, 'test.php')
                        && str_contains($prompt, 'SQL injection')
                        && str_contains($prompt, 'Use parameterized queries')
                        && str_contains($prompt, 'Current source code:');
                }
            ))
            ->willReturn($fixedSource);

        $agent = new FixAgent($llm);

        $finding = new ReviewFinding(
            1,
            'high',
            'security',
            'SQL injection',
            'Use parameterized queries',
        );

        $result = $agent->fix(
            'test.php',
            '<?php echo "Hello";',
            new ReviewResult([$finding]),
        );

        self::assertSame($fixedSource, $result);
    }

    public function testPassesAllReviewFindingsToLlm(): void
    {
        $llm = $this->createMock(LlmInterface::class);

        $llm
            ->expects(self::once())
            ->method('generate')
            ->with(self::callback(
                static function (string $prompt): bool {
                    return str_contains($prompt, 'SQL injection')
                        && str_contains($prompt, 'SQL security fix')
                        && str_contains($prompt, 'Null pointer')
                        && str_contains($prompt, 'Add validation');
                }
            ))
            ->willReturn('<?php');

        $agent = new FixAgent($llm);

        $findings = [
            new ReviewFinding(
                1,
                'critical',
                'security',
                'SQL injection',
                'SQL security fix',
            ),
            new ReviewFinding(
                1,
                'high',
                'bug',
                'Null pointer',
                'Add validation',
            ),
        ];

        $result = $agent->fix(
            'example.php',
            '<?php',
            new ReviewResult($findings),
        );

        self::assertSame('<?php', $result);
    }

    public function testReturnsExactlyTheSourceReturnedByLlm(): void
    {
        $llm = $this->createMock(LlmInterface::class);
        $fixedSource = 
        <<<'PHP'
         <?php function calculate(): int { return 42; } 
PHP; 
        $llm ->expects(self::once()) 
        ->method('generate') 
        ->willReturn($fixedSource); 
        $agent = new FixAgent($llm); 
        $finding = new ReviewFinding( 
            1,
            'high', 
            'bug', 
            'Incorrect return value', 
            'Return the correct value', 
            ); 
            $result = 
            $agent->fix( 
                'example.php', 
                '<?php return 0;', 
                new ReviewResult([$finding]), 
                ); 
                self::assertSame($fixedSource, $result); 
    }
}