<?php

declare(strict_types=1);

namespace App\Tests\AI\Workflow;

use App\AI\Agent\FixAgent\FixAgentInterface;
use App\AI\File\SourceFileProviderInterface;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
use App\AI\Workflow\FixWorkflow;
use PHPUnit\Framework\TestCase;

final class FixWorkflowTest extends TestCase
{
    public function testDoesNotModifyFilesWithoutDeveloperApproval(): void
    {
        $fixAgent = $this->createMock(FixAgentInterface::class);
        $fileProvider = $this->createMock(SourceFileProviderInterface::class);

        $fixAgent
            ->expects(self::never())
            ->method('fix');

        $fileProvider
            ->expects(self::never())
            ->method('write');

        $workflow = new FixWorkflow(
            $fixAgent,
            $fileProvider,
        );

        $review = new ReviewResult([
            new ReviewFinding(
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

        $review = new ReviewResult([
            new ReviewFinding(
                'high',
                'bug',
                'Example bug',
                'Fix the bug',
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
            $result->getFixedFiles()
        );
    }

    public function testSkipsFilesWithoutReviewFindings(): void
    {
        $fixAgent = $this->createMock(FixAgentInterface::class);
        $fileProvider = $this->createMock(SourceFileProviderInterface::class);

        $fixAgent
            ->expects(self::never())
            ->method('fix');

        $fileProvider
            ->expects(self::never())
            ->method('read');

        $fileProvider
            ->expects(self::never())
            ->method('write');

        $workflow = new FixWorkflow(
            $fixAgent,
            $fileProvider,
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

        $review = new ReviewResult([
            new ReviewFinding(
                'medium',
                'maintainability',
                'Example issue',
                'Improve the code',
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

        $workflow = new FixWorkflow(
            $fixAgent,
            $fileProvider,
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
}