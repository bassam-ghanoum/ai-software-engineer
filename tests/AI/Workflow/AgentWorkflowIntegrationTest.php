<?php

declare(strict_types=1);

namespace App\Tests\AI\Workflow;

use App\AI\Agent\CodeReviewAgentInterface;
use App\AI\Agent\FixAgent\FixAgentInterface;
use App\AI\Agent\FixAgent\FixScopeValidatorInterface;
use App\AI\File\SourceFileProviderInterface;
use App\AI\File\SourceValidatorInterface;
use App\AI\Git\ChangedCodeProviderInterface;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
use App\AI\Workflow\CodeReviewWorkflow;
use App\AI\Workflow\FixWorkflow;
use PHPUnit\Framework\TestCase;

final class AgentWorkflowIntegrationTest extends TestCase
{
    public function testAgentOneReviewIsPassedToAgentTwoAndFileIsFixedAfterApproval(): void
    {
        $filePath = 'test1.php';

        $originalSource = <<<'PHP'
<?php

function test(): array
{
    echo *"Hello, World!";
}
PHP;

        $fixedSource = <<<'PHP'
<?php

function test(): array
{
    echo "Hello, World!";
}
PHP;

        $reviewFinding = new ReviewFinding(
            'high',
            'bug',
            'Invalid PHP syntax.',
            'Replace the invalid echo syntax with valid PHP syntax.',
        );

        $reviewResult = new ReviewResult([
            $reviewFinding,
        ]);

        $changedCodeProvider = $this->createMock(
            ChangedCodeProviderInterface::class
        );

        $changedCodeProvider
            ->expects(self::once())
            ->method('getChangedPhpFiles')
            ->with('HEAD~1', 'HEAD')
            ->willReturn([
                $filePath => $originalSource,
            ]);

        $reviewAgent = $this->createMock(
            CodeReviewAgentInterface::class
        );

        $reviewAgent
            ->expects(self::once())
            ->method('review')
            ->with(
                $filePath,
                $originalSource,
            )
            ->willReturn($reviewResult);

        $codeReviewWorkflow = new CodeReviewWorkflow(
            $changedCodeProvider,
            $reviewAgent,
        );

        $reviews = $codeReviewWorkflow->reviewChanges(
            'HEAD~1',
            'HEAD',
        );

        self::assertArrayHasKey($filePath, $reviews);
        self::assertSame($reviewResult, $reviews[$filePath]);

        $sourceFileProvider = $this->createMock(
            SourceFileProviderInterface::class
        );

        $sourceFileProvider
            ->expects(self::once())
            ->method('exists')
            ->with($filePath)
            ->willReturn(true);

        $sourceFileProvider
            ->expects(self::once())
            ->method('read')
            ->with($filePath)
            ->willReturn($originalSource);

        $sourceValidator = $this->createMock(
            SourceValidatorInterface::class
        );

        $sourceValidator
            ->expects(self::once())
            ->method('validate')
            ->with(
                $filePath,
                $fixedSource,
            );

        $fixScopeValidator = $this->createPassingFixScopeValidator();

        $sourceFileProvider
            ->expects(self::once())
            ->method('write')
            ->with(
                $filePath,
                $fixedSource,
            );

        $fixAgent = $this->createMock(
            FixAgentInterface::class
        );

        $fixAgent
            ->expects(self::once())
            ->method('fix')
            ->with(
                $filePath,
                $originalSource,
                $reviewResult,
            )
            ->willReturn($fixedSource);

        $fixWorkflow = new FixWorkflow(
            $fixAgent,
            $sourceFileProvider,
            $sourceValidator,
            $fixScopeValidator,
        );

        $result = $fixWorkflow->fix(
            $reviews,
            true,
        );

        self::assertTrue($result->hasChanges());
        self::assertSame(
            [$filePath => $fixedSource],
            $result->getFixedFiles(),
        );
    }

    public function testAgentTwoDoesNotModifyFilesWithoutDeveloperApproval(): void
    {
        $filePath = 'test1.php';

        $reviewResult = new ReviewResult([
            new ReviewFinding(
                'high',
                'bug',
                'Invalid PHP syntax.',
                'Fix the syntax.',
            ),
        ]);

        $fixAgent = $this->createMock(
            FixAgentInterface::class
        );

        $fixAgent
            ->expects(self::never())
            ->method('fix');

        $fixScopeValidator = $this->createPassingFixScopeValidator();

        $sourceFileProvider = $this->createMock(
            SourceFileProviderInterface::class
        );

        $sourceFileProvider
            ->expects(self::never())
            ->method('exists');

        $sourceFileProvider
            ->expects(self::never())
            ->method('read');

        $sourceFileProvider
            ->expects(self::never())
            ->method('write');

        $sourceValidator = $this->createMock(
            SourceValidatorInterface::class
        );

        $sourceValidator
            ->expects(self::never())
            ->method('validate');

        $fixWorkflow = new FixWorkflow(
            $fixAgent,
            $sourceFileProvider,
            $sourceValidator,
            $fixScopeValidator,
        );

        $result = $fixWorkflow->fix(
            [$filePath => $reviewResult],
            false,
        );

        self::assertFalse($result->hasChanges());
        self::assertSame([], $result->getFixedFiles());
    }

private function createPassingFixScopeValidator(): FixScopeValidatorInterface
{
    return $this->createStub(FixScopeValidatorInterface::class);
}
}