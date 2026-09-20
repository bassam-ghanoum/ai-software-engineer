<?php

declare(strict_types=1);

namespace App\Tests\AI\Workflow;

use App\AI\Agent\FixAgent\FixAgentInterface;
use App\AI\Agent\FixAgent\FixScopeValidatorInterface;
use App\AI\File\SourceFileProviderInterface;
use App\AI\File\SourceValidatorInterface;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
use App\AI\Workflow\FixWorkflow;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FixWorkflowTest extends TestCase
{
    public function testDoesNotModifyFilesWithoutDeveloperApproval(): void
    {
        $fixAgent = $this->createMock(FixAgentInterface::class);
        $fileProvider = $this->createMock(SourceFileProviderInterface::class);
        $sourceValidator = $this->createMock(SourceValidatorInterface::class);
        $fixScopeValidator = $this->createPassingFixScopeValidator();

        $fixAgent
            ->expects(self::never())
            ->method('fix');

        $fileProvider
            ->expects(self::never())
            ->method('write');

        $sourceValidator
            ->expects(self::never())
            ->method('validate');

        $workflow = new FixWorkflow(
            $fixAgent,
            $fileProvider,
            $sourceValidator,
            $fixScopeValidator,
        );

        $review = new ReviewResult([
            new ReviewFinding(
                1,
                'high',
                'bug',
                'Example bug',
                'Fix the bug',
            ),
        ]);

        $result = $workflow->fix(
            [
                'src/Test.php' => $review,
            ],
            false,
        );

        self::assertFalse($result->hasChanges());
        self::assertSame(0, $result->count());
    }

    public function testAppliesFixAfterDeveloperApproval(): void
    {
        $fixAgent = $this->createMock(FixAgentInterface::class);
        $fileProvider = $this->createMock(SourceFileProviderInterface::class);
        $sourceValidator = $this->createMock(SourceValidatorInterface::class);
        $fixScopeValidator = $this->createPassingFixScopeValidator();

        $review = new ReviewResult([
            new ReviewFinding(
                'high',
                'bug',
                'Example bug',
                'Fix the bug.',
            ),
        ]);

        $source = <<<'PHP'
<?php

echo "Broken";
PHP;

        $fixedSource = <<<'PHP'
<?php

echo "Fixed";
PHP;

        $fileProvider
            ->expects(self::once())
            ->method('exists')
            ->with('src/Test.php')
            ->willReturn(true);

        $fileProvider
            ->expects(self::once())
            ->method('read')
            ->with('src/Test.php')
            ->willReturn($source);

        $fixAgent
            ->expects(self::once())
            ->method('fix')
            ->with(
                'src/Test.php',
                $source,
                $review,
            )
            ->willReturn($fixedSource);

        $sourceValidator
            ->expects(self::once())
            ->method('validate')
            ->with(
                'src/Test.php',
                $fixedSource,
            );

        $fileProvider
            ->expects(self::once())
            ->method('write')
            ->with(
                'src/Test.php',
                $fixedSource,
            );

        $workflow = new FixWorkflow(
            $fixAgent,
            $fileProvider,
            $sourceValidator,
            $fixScopeValidator,
        );

        $result = $workflow->fix(
            [
                'src/Test.php' => $review,
            ],
            true,
        );

        self::assertTrue($result->hasChanges());
        self::assertSame(1, $result->count());

        self::assertSame(
            [
                'src/Test.php' => $fixedSource,
            ],
            $result->getFixedFiles(),
        );
    }

    public function testSkipsFilesWithoutReviewFindings(): void
    {
        $fixAgent = $this->createMock(FixAgentInterface::class);
        $fileProvider = $this->createMock(SourceFileProviderInterface::class);
        $sourceValidator = $this->createMock(SourceValidatorInterface::class);
        $fixScopeValidator = $this->createPassingFixScopeValidator();

        $fixAgent
            ->expects(self::never())
            ->method('fix');

        $fileProvider
            ->expects(self::never())
            ->method('read');

        $fileProvider
            ->expects(self::never())
            ->method('write');

        $sourceValidator
            ->expects(self::never())
            ->method('validate');

        $workflow = new FixWorkflow(
            $fixAgent,
            $fileProvider,
            $sourceValidator,
            $fixScopeValidator,
        );

        $result = $workflow->fix(
            [
                'src/Test.php' => new ReviewResult([]),
            ],
            true,
        );

        self::assertFalse($result->hasChanges());
    }

    public function testDoesNotWriteWhenFixAgentReturnsOriginalSource(): void
    {
        $fixAgent = $this->createMock(FixAgentInterface::class);
        $fileProvider = $this->createMock(SourceFileProviderInterface::class);
        $sourceValidator = $this->createMock(SourceValidatorInterface::class);
        $fixScopeValidator = $this->createPassingFixScopeValidator();

        $review = new ReviewResult([
            new ReviewFinding(
                'medium',
                'maintainability',
                'Example issue',
                'Improve the code.',
            ),
        ]);

        $source = '<?php echo "Hello";';

        $fileProvider
            ->expects(self::once())
            ->method('exists')
            ->willReturn(true);

        $fileProvider
            ->expects(self::once())
            ->method('read')
            ->willReturn($source);

        $fixAgent
            ->expects(self::once())
            ->method('fix')
            ->willReturn($source);

        $fileProvider
            ->expects(self::never())
            ->method('write');

        $sourceValidator
            ->expects(self::never())
            ->method('validate');

        $workflow = new FixWorkflow(
            $fixAgent,
            $fileProvider,
            $sourceValidator,
            $fixScopeValidator,
        );

        $result = $workflow->fix(
            [
                'src/Test.php' => $review,
            ],
            true,
        );

        self::assertFalse($result->hasChanges());
        self::assertSame(0, $result->count());
    }

    public function testThrowsExceptionWhenSourceFileDoesNotExist(): void
    {
        $fixAgent = $this->createMock(FixAgentInterface::class);
        $fileProvider = $this->createMock(SourceFileProviderInterface::class);
        $sourceValidator = $this->createMock(SourceValidatorInterface::class);
        $fixScopeValidator = $this->createPassingFixScopeValidator();

        $review = new ReviewResult([
            new ReviewFinding(
                'high',
                'bug',
                'Example bug',
                'Fix the bug.',
            ),
        ]);

        $fileProvider
            ->expects(self::once())
            ->method('exists')
            ->with('src/Missing.php')
            ->willReturn(false);

        $fixAgent
            ->expects(self::never())
            ->method('fix');

        $fileProvider
            ->expects(self::never())
            ->method('read');

        $fileProvider
            ->expects(self::never())
            ->method('write');

        $sourceValidator
            ->expects(self::never())
            ->method('validate');

        $workflow = new FixWorkflow(
            $fixAgent,
            $fileProvider,
            $sourceValidator,
            $fixScopeValidator,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Source file does not exist: src/Missing.php'
        );

        $workflow->fix(
            [
                'src/Missing.php' => $review,
            ],
            true,
        );
    }

    public function testFixesMultipleFilesIndependently(): void
    {
        $fixAgent = $this->createMock(FixAgentInterface::class);
        $fileProvider = $this->createMock(SourceFileProviderInterface::class);
        $sourceValidator = $this->createMock(SourceValidatorInterface::class);
        $fixScopeValidator = $this->createPassingFixScopeValidator();

        $review1 = new ReviewResult([
            new ReviewFinding(
                'high',
                'bug',
                'Bug in file one',
                'Fix file one.',
            ),
        ]);

        $review2 = new ReviewResult([
            new ReviewFinding(
                'medium',
                'maintainability',
                'Issue in file two',
                'Improve file two.',
            ),
        ]);

        $source1 = '<?php echo "One";';
        $source2 = '<?php echo "Two";';

        $fixedSource1 = '<?php echo "Fixed One";';
        $fixedSource2 = '<?php echo "Fixed Two";';

        $fileProvider
            ->method('exists')
            ->willReturn(true);

        $fileProvider
            ->expects(self::exactly(2))
            ->method('read')
            ->willReturnMap([
                ['src/One.php', $source1],
                ['src/Two.php', $source2],
            ]);

        $fixAgent
            ->expects(self::exactly(2))
            ->method('fix')
            ->willReturnMap([
                ['src/One.php', $source1, $review1, $fixedSource1],
                ['src/Two.php', $source2, $review2, $fixedSource2],
            ]);

        $sourceValidator
            ->expects(self::exactly(2))
            ->method('validate')
            ->willReturnCallback(
                function (
                    string $filePath,
                    string $source,
                ) use (
                    $fixedSource1,
                    $fixedSource2,
                ): void {
                    if ($filePath === 'src/One.php') {
                        self::assertSame($fixedSource1, $source);

                        return;
                    }

                    if ($filePath === 'src/Two.php') {
                        self::assertSame($fixedSource2, $source);

                        return;
                    }

                    self::fail('Unexpected file: ' . $filePath);
                }
            );

        $fileProvider
            ->expects(self::exactly(2))
            ->method('write')
            ->willReturnCallback(
                function (
                    string $filePath,
                    string $source,
                ) use (
                    $fixedSource1,
                    $fixedSource2,
                ): void {
                    if ($filePath === 'src/One.php') {
                        self::assertSame($fixedSource1, $source);

                        return;
                    }

                    if ($filePath === 'src/Two.php') {
                        self::assertSame($fixedSource2, $source);

                        return;
                    }

                    self::fail('Unexpected file: ' . $filePath);
                }
            );

        $workflow = new FixWorkflow(
            $fixAgent,
            $fileProvider,
            $sourceValidator,
            $fixScopeValidator,
        );

        $result = $workflow->fix(
            [
                'src/One.php' => $review1,
                'src/Two.php' => $review2,
            ],
            true,
        );

        self::assertTrue($result->hasChanges());
        self::assertSame(2, $result->count());

        self::assertSame(
            [
                'src/One.php' => $fixedSource1,
                'src/Two.php' => $fixedSource2,
            ],
            $result->getFixedFiles(),
        );
    }

    public function testDoesNotWriteWhenValidationFails(): void
    {
        $fixAgent = $this->createMock(FixAgentInterface::class);
        $fileProvider = $this->createMock(SourceFileProviderInterface::class);
        $sourceValidator = $this->createMock(SourceValidatorInterface::class);
        $fixScopeValidator = $this->createPassingFixScopeValidator();

        $review = new ReviewResult([
            new ReviewFinding(
                'critical',
                'bug',
                'Example critical issue',
                'Fix the issue.',
            ),
        ]);

        $source = '<?php echo "Broken";';
        $invalidFixedSource = '<?php echo "Still broken"';

        $fileProvider
            ->expects(self::once())
            ->method('exists')
            ->with('src/Test.php')
            ->willReturn(true);

        $fileProvider
            ->expects(self::once())
            ->method('read')
            ->with('src/Test.php')
            ->willReturn($source);

        $fixAgent
            ->expects(self::once())
            ->method('fix')
            ->with(
                'src/Test.php',
                $source,
                $review,
            )
            ->willReturn($invalidFixedSource);

        $sourceValidator
            ->expects(self::once())
            ->method('validate')
            ->with(
                'src/Test.php',
                $invalidFixedSource,
            )
            ->willThrowException(
                new RuntimeException('Invalid PHP source.')
            );

        $fileProvider
            ->expects(self::never())
            ->method('write');

        $workflow = new FixWorkflow(
            $fixAgent,
            $fileProvider,
            $sourceValidator,
            $fixScopeValidator,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid PHP source.');

        $workflow->fix(
            [
                'src/Test.php' => $review,
            ],
            true,
        );
    }

    public function testFixIsRejectedWhenScopeValidatorFails(): void
    {
        $fixAgent = $this->createMock(FixAgentInterface::class);
        $sourceFileProvider = $this->createMock(
            SourceFileProviderInterface::class
        );
        $sourceValidator = $this->createMock(
            SourceValidatorInterface::class
        );
        $fixScopeValidator = $this->createMock(
            FixScopeValidatorInterface::class
        );

        $reviewResult = $this->createReviewResult();

        $originalSource = <<<'PHP'
<?php

function test(): string
{
    echo *"Hello, World!";

    return [];
}
PHP;

        $fixedSource = <<<'PHP'
<?php

function test(): string {

    echo "Hello, World!";

    return '';
}
PHP;

        $sourceFileProvider
            ->expects(self::once())
            ->method('exists')
            ->with('fixtures/test1.php')
            ->willReturn(true);

        $sourceFileProvider
            ->expects(self::once())
            ->method('read')
            ->with('fixtures/test1.php')
            ->willReturn($originalSource);

        $fixAgent
            ->expects(self::once())
            ->method('fix')
            ->with(
                'fixtures/test1.php',
                $originalSource,
                $reviewResult,
            )
            ->willReturn($fixedSource);

        $sourceValidator
            ->expects(self::once())
            ->method('validate')
            ->with(
                'fixtures/test1.php',
                $fixedSource,
            );

        $fixScopeValidator
            ->expects(self::once())
            ->method('validate')
            ->with(
                'fixtures/test1.php',
                $originalSource,
                $fixedSource,
                $reviewResult,
            )
            ->willThrowException(
                new RuntimeException(
                    'Fix rejected because it contains changes outside the review scope.'
                )
            );

        $sourceFileProvider
            ->expects(self::never())
            ->method('write');

        $workflow = new FixWorkflow(
            $fixAgent,
            $sourceFileProvider,
            $sourceValidator,
            $fixScopeValidator,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Fix rejected because it contains changes outside the review scope.'
        );

        $workflow->fix(
            [
                'fixtures/test1.php' => $reviewResult,
            ],
            true,
        );
    }

    private function createReviewResult(): ReviewResult
    {
        return new ReviewResult([
            new ReviewFinding(
                'critical',
                'bug',
                'Syntax error caused by the unexpected * operator.',
                'Remove the * operator.',
            ),
        ]);
    }

private function createPassingFixScopeValidator(): FixScopeValidatorInterface
{
    return $this->createStub(FixScopeValidatorInterface::class);
}
}