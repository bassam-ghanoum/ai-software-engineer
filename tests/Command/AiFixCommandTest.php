<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\AI\Agent\FixAgent\FixResult;
use App\AI\DTO\Agent\FixAgent\FixedFiles;
use App\AI\Git\GitInterface;
use App\AI\Review\ReviewResultSerializer;
use App\AI\DTO\Review\ReviewBatch;
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

    public function testItUsesTheLiveReviewWorkflowWhenNoPersistedFileIsProvided(): void
    {
        $codeReviewWorkflow = $this->createMock(CodeReviewWorkflowInterface::class);
        $codeReviewWorkflow
            ->expects(self::once())
            ->method('reviewChanges')
            ->with('FROM_SHA', 'TO_SHA')
            ->willReturn(new ReviewBatch([
                'fixtures/test1.php' => new \App\AI\Review\ReviewResult([
                    new \App\AI\Review\ReviewFinding(
                        line: 5,
                        severity: 'critical',
                        category: 'bug',
                        message: 'Test bug.',
                        suggestion: 'Fix the bug.',
                    ),
                ]),
            ]));

        $fixWorkflow = $this->createMock(FixWorkflowInterface::class);
        $fixWorkflow
            ->expects(self::once())
            ->method('fix')
            ->with(
                self::callback(static fn (ReviewBatch $reviews): bool => $reviews->getReview('fixtures/test1.php')?->hasFindings() ?? false),
                true,
            )
            ->willReturn(new \App\AI\Agent\FixAgent\FixResult(new \App\AI\DTO\Agent\FixAgent\FixedFiles([
                'fixtures/test1.php' => '<?php echo "fixed";',
            ])));

        $git = $this->createMock(GitInterface::class);
        $git
            ->expects(self::once())
            ->method('assertWorkingTreeMatchesRevision')
            ->with('TO_SHA');

        $command = new AiFixCommand(
            $codeReviewWorkflow,
            new ReviewResultSerializer(),
            $fixWorkflow,
            $git,
        );

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('ai:fix'));
        $exitCode = $commandTester->execute([
            'from' => 'FROM_SHA',
            'to' => 'TO_SHA',
            '--approved' => true,
        ]);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Reviewing changes from FROM_SHA to TO_SHA...', $commandTester->getDisplay());
        self::assertStringContainsString('Fixed 1 file(s).', $commandTester->getDisplay());
    }

    public function testItStopsWhenThePersistedReviewContainsNoFindings(): void
    {
        $codeReviewWorkflow = $this->createMock(CodeReviewWorkflowInterface::class);
        $codeReviewWorkflow->expects(self::never())->method('reviewChanges');

        $fixWorkflow = $this->createMock(FixWorkflowInterface::class);
        $fixWorkflow->expects(self::never())->method('fix');

        $reviewFile = tempnam(sys_get_temp_dir(), 'ai-review-');
        self::assertNotFalse($reviewFile);

        file_put_contents($reviewFile, json_encode([
            'commit_sha' => 'TO_SHA',
            'reviews' => [
                'fixtures/test1.php' => [
                    'findings' => [],
                ],
            ],
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

            $exitCode = $commandTester->execute([
                'from' => 'FROM_SHA',
                'to' => 'TO_SHA',
                '--approved' => true,
                '--review-file' => $reviewFile,
            ]);

            self::assertSame(0, $exitCode);
            self::assertStringContainsString('No issues found. Nothing to fix.', $commandTester->getDisplay());
        } finally {
            if (is_file($reviewFile)) {
                unlink($reviewFile);
            }
        }
    }

    public function testItRejectsMissingPersistedReviewFile(): void
    {
        $missingFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ai-review-missing-' . uniqid('', true) . '.json';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Review result file does not exist: ' . $missingFile);

        $command = new AiFixCommand(
            $this->createMock(CodeReviewWorkflowInterface::class),
            new ReviewResultSerializer(),
            $this->createMock(FixWorkflowInterface::class),
            $this->createStub(GitInterface::class),
        );

        $application = new Application();
        $application->addCommand($command);

        $commandTester = new CommandTester($application->find('ai:fix'));
        $commandTester->execute([
            'from' => 'FROM_SHA',
            'to' => 'TO_SHA',
            '--approved' => true,
            '--review-file' => $missingFile,
        ]);
    }
}
