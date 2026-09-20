<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\AI\Agent\FixAgent\FixResult;
use App\AI\Review\ReviewResultSerializer;
use App\AI\Workflow\CodeReviewWorkflowInterface;
use App\AI\Workflow\FixWorkflowInterface;
use App\Command\AiFixCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class AiFixCommandTest extends TestCase
{
    public function testItUsesPersistedReviewWithoutRunningAgentOneAgain(): void
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
                    static function (array $reviews): bool {
                        return isset($reviews['fixtures/test1.php'])
                            && $reviews['fixtures/test1.php']->hasFindings();
                    }
                ),
                true,
            )
            ->willReturn(new FixResult([]));

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
}