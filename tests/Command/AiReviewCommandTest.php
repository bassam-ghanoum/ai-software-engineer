<?php

namespace App\Tests\Command;

use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
use App\AI\Workflow\CodeReviewWorkflowInterface;
use App\Command\AiReviewCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class AiReviewCommandTest extends TestCase
{
    public function testItReviewsChangedFilesAndDisplaysFindings(): void
    {
        $workflow = $this->createMock(CodeReviewWorkflowInterface::class);

        $finding = new ReviewFinding(
            severity: 'high',
            category: 'security',
            message: 'User input is used directly in a database query.',
            suggestion: 'Use prepared statements or Doctrine parameters.',
        );

        $workflow
            ->expects(self::once())
            ->method('reviewChanges')
            ->with('HEAD~1', 'HEAD')
            ->willReturn([
                'src/Service/UserService.php' => new ReviewResult([
                    $finding,
                ]),
            ]);

        $command = new AiReviewCommand($workflow);

        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            'from' => 'HEAD~1',
            'to' => 'HEAD',
        ]);

        self::assertSame(0, $exitCode);

        $output = $commandTester->getDisplay();

        self::assertStringContainsString(
            'Reviewing PHP changes: HEAD~1 → HEAD',
            $output
        );

        self::assertStringContainsString(
            'File: src/Service/UserService.php',
            $output
        );

        self::assertStringContainsString(
            '[HIGH] [security]',
            $output
        );

        self::assertStringContainsString(
            'User input is used directly in a database query.',
            $output
        );

        self::assertStringContainsString(
            'Suggestion: Use prepared statements or Doctrine parameters.',
            $output
        );
    }

    public function testItDisplaysNoFindings(): void
    {
        $workflow = $this->createMock(CodeReviewWorkflowInterface::class);

        $workflow
            ->expects(self::once())
            ->method('reviewChanges')
            ->with('HEAD~1', 'HEAD')
            ->willReturn([
                'src/Service/UserService.php' => new ReviewResult([]),
            ]);

        $command = new AiReviewCommand($workflow);

        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            'from' => 'HEAD~1',
            'to' => 'HEAD',
        ]);

        self::assertSame(0, $exitCode);

        $output = $commandTester->getDisplay();

        self::assertStringContainsString(
            'File: src/Service/UserService.php',
            $output
        );

        self::assertStringContainsString(
            'No findings.',
            $output
        );
    }

    public function testItDisplaysMessageWhenNoPhpFilesChanged(): void
    {
        $workflow = $this->createMock(CodeReviewWorkflowInterface::class);

        $workflow
            ->expects(self::once())
            ->method('reviewChanges')
            ->with('HEAD~1', 'HEAD')
            ->willReturn([]);

        $command = new AiReviewCommand($workflow);

        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([
            'from' => 'HEAD~1',
            'to' => 'HEAD',
        ]);

        self::assertSame(0, $exitCode);

        self::assertStringContainsString(
            'No changed PHP files found.',
            $commandTester->getDisplay()
        );
    }
}
