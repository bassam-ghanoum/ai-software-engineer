<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\AI\Agent\FixAgent\FixResult;
use App\AI\Agent\FixAgent\DTO\FixedFiles;
use App\AI\Git\GitInterface;
use App\AI\Review\ReviewResultSerializer;
use App\AI\Review\DTO\ReviewBatch;
use App\AI\Workflow\CodeReviewWorkflowInterface;
use App\AI\Workflow\FixWorkflowInterface;
use App\Command\AiFixCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class AiFixCommandTest extends TestCase
{
    public function testItUsesPersistedReviewWithoutRunningAgentAgain(): void
    {
        $codeReviewWorkflow = $this->createMock(
            CodeReviewWorkflowInterface::class
        );

        $codeReviewWorkflow
            ->expects(self::never())
            ->method('reviewChanges');

        $fixWorkflow = $this->createMock(
            FixWorkflowInterface::class
        );

        $fixWorkflow
            ->expects(self::once())
            ->method('fix')
            ->with(
                self::callback(
                    static function (ReviewBatch $reviews): bool {
                        return $reviews->getReview('fixtures/test1.php')?->hasFindings()
                            ?? false;
                    }
                ),
                true,
            )
            ->willReturn(new FixResult(new FixedFiles([])));

        $serializer = new ReviewResultSerializer();

        $reviewFile = tempnam(
            sys_get_temp_dir(),
            'ai-review-',
        );

        self::assertNotFalse($reviewFile);

        $reviewJson = <<<JSON
{
    "commit_sha": "HEAD",
    "reviews": {
        "fixtures/test1.php": {
            "findings": [
                {
                    "line": 5,
                    "severity": "critical",
                    "category": "bug",
                    "message": "Test bug.",
                    "suggestion": "Fix the bug."
                }
            ]
        }
    }
}

JSON;

        file_put_contents(
            $reviewFile,
            $reviewJson,
        );

        try {
            $command = new AiFixCommand(
                $codeReviewWorkflow,
                $serializer,
                $fixWorkflow,
                $this->createStub(GitInterface::class),
            );

            $application = new Application();

            $application->addCommand($command);

            $commandTester = new CommandTester(
                $application->find('ai:fix')
            );

            $exitCode = $commandTester->execute([
                'from' => 'FROM_SHA',
                'to' => 'HEAD',
                '--approved' => true,
                '--review-file' => $reviewFile,
            ]);

            self::assertSame(
                0,
                $exitCode,
            );

            self::assertStringContainsString(
                'Using persisted AI review',
                $commandTester->getDisplay(),
            );
        } finally {
            if (is_file($reviewFile)) {
                unlink($reviewFile);
            }
        }
    }

    public function testItRejectsPersistedReviewBoundToHeadWhenTargetDiffers(): void
    {
        $codeReviewWorkflow = $this->createMock(CodeReviewWorkflowInterface::class);
        $codeReviewWorkflow
            ->expects(self::never())
            ->method('reviewChanges');

        $fixWorkflow = $this->createMock(FixWorkflowInterface::class);
        $fixWorkflow
            ->expects(self::never())
            ->method('fix');

        $reviewFile = tempnam(sys_get_temp_dir(), 'ai-review-');
        self::assertNotFalse($reviewFile);

        file_put_contents($reviewFile, json_encode([
            'commit_sha' => 'HEAD',
            'reviews' => [],
        ], JSON_THROW_ON_ERROR));

        try {
            $command = new AiFixCommand(
                $codeReviewWorkflow,
                new ReviewResultSerializer(),
                $fixWorkflow,
                $this->createStub(GitInterface::class),
            );
            $application = new Application();
            $application->addCommand($command);
            $commandTester = new CommandTester($application->find('ai:fix'));

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage(
                'Review result belongs to commit "HEAD", but the current fix target is "TARGET_SHA".',
            );

            $commandTester->execute([
                'from' => 'FROM_SHA',
                'to' => 'TARGET_SHA',
                '--approved' => true,
                '--review-file' => $reviewFile,
            ]);
        } finally {
            unlink($reviewFile);
        }
    }
}
