<?php

declare(strict_types=1);

namespace App\Tests\AI\Workflow;

use App\AI\Agent\CodeReviewAgentInterface;
use App\AI\Git\ChangedCodeProviderInterface;
use App\AI\DTO\Git\ChangedPhpFiles;
use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
use App\AI\Workflow\CodeReviewWorkflow;
use PHPUnit\Framework\TestCase;

final class CodeReviewWorkflowTest extends TestCase
{
    public function testItReviewsEveryChangedPhpFile(): void
    {
        $changedCodeProvider = $this->createMock(
            ChangedCodeProviderInterface::class
        );

        $reviewAgent = $this->createMock(CodeReviewAgentInterface::class);

        $changedCodeProvider
            ->expects(self::once())
            ->method('getChangedPhpFiles')
            ->with('HEAD~1', 'HEAD')
            ->willReturn(new ChangedPhpFiles([
                'src/Service/UserService.php' => '<?php class UserService {}',
                'src/Repository/UserRepository.php' => '<?php class UserRepository {}',
            ]));

        $reviewAgent
            ->expects(self::exactly(2))
            ->method('review')
            ->willReturnCallback(
                static function (string $code): ReviewResult {
                    if (str_contains($code, 'UserService')) {
                        return new ReviewResult([
                            new ReviewFinding(
                                1,
                                severity: 'high',
                                category: 'security',
                                message: 'Security issue in service.',
                                suggestion: 'Validate the input.'
                            ),
                        ]);
                    }

                    return new ReviewResult([]);
                }
            );

        $workflow = new CodeReviewWorkflow(
            $changedCodeProvider,
            $reviewAgent
        );

        $results = $workflow->reviewChanges(
            'HEAD~1',
            'HEAD'
        );

        self::assertCount(2, $results);

        self::assertNotNull($results->getReview('src/Service/UserService.php'));
        self::assertNotNull($results->getReview('src/Repository/UserRepository.php'));

        self::assertTrue(
            $results->getReview('src/Service/UserService.php')?->hasFindings()
        );

        self::assertFalse(
            $results->getReview('src/Repository/UserRepository.php')?->hasFindings()
        );
    }

    public function testItReturnsEmptyResultsWhenNothingChanged(): void
    {
        $changedCodeProvider = $this->createMock(
            ChangedCodeProviderInterface::class
        );

        $reviewAgent = $this->createMock(CodeReviewAgentInterface::class);

        $changedCodeProvider
            ->expects(self::once())
            ->method('getChangedPhpFiles')
            ->with('HEAD~1', 'HEAD')
            ->willReturn(new ChangedPhpFiles([]));

        $reviewAgent
            ->expects(self::never())
            ->method('review');

        $workflow = new CodeReviewWorkflow(
            $changedCodeProvider,
            $reviewAgent
        );

        $results = $workflow->reviewChanges(
            'HEAD~1',
            'HEAD'
        );

        self::assertTrue($results->isEmpty());
    }
}