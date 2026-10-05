<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\AI\Review\ReviewFinding;
use App\AI\Review\ReviewResult;
use App\AI\DTO\Review\ReviewBatch;
use App\AI\Review\ReviewResultSerializer;
use App\AI\Workflow\CodeReviewWorkflowInterface;
use App\Command\AiReviewCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class AiReviewCommandTest extends TestCase
{
    public function testItWritesSerializedReviewResultToOutputFile(): void
    {
        $workflow = $this->createMock(
            CodeReviewWorkflowInterface::class
        );

        $workflow
            ->expects(self::once())
            ->method('reviewChanges')
            ->with('FROM_SHA', 'TO_SHA')
            ->willReturn(new ReviewBatch([
                'fixtures/test1.php' => new ReviewResult([
                    new ReviewFinding(
                        line: 4,
                        severity: 'high',
                        category: 'bug',
                        message: 'Test finding.',
                        suggestion: 'Fix the bug.',
                    ),
                ]),
            ]));

        $serializer = new ReviewResultSerializer();

        $outputFile = tempnam(
            sys_get_temp_dir(),
            'ai-review-',
        );

        self::assertNotFalse($outputFile);

        try {
            $command = new AiReviewCommand(
                $workflow,
                $serializer,
            );

            $application = new Application();

            $application->addCommand($command);

            $commandTester = new CommandTester(
                $application->find('ai:review')
            );

            $exitCode = $commandTester->execute([
                'from' => 'FROM_SHA',
                'to' => 'TO_SHA',
                '--output' => $outputFile,
            ]);

            self::assertSame(
                0,
                $exitCode,
            );

            self::assertFileExists($outputFile);

            $json = file_get_contents($outputFile);

            self::assertIsString($json);

            $data = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            self::assertSame(
                'TO_SHA',
                $data['commit_sha'],
            );

            self::assertArrayHasKey(
                'fixtures/test1.php',
                $data['reviews'],
            );

            self::assertSame(
                4,
                $data['reviews']['fixtures/test1.php']['findings'][0]['line'],
            );
        } finally {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function testItCanWriteEmptyReviewResult(): void
    {
        $workflow = $this->createMock(
            CodeReviewWorkflowInterface::class
        );

        $workflow
            ->expects(self::once())
            ->method('reviewChanges')
            ->with('FROM_SHA', 'TO_SHA')
            ->willReturn(new ReviewBatch([]));

        $serializer = new ReviewResultSerializer();

        $outputFile = tempnam(
            sys_get_temp_dir(),
            'ai-review-',
        );

        self::assertNotFalse($outputFile);

        try {
            $command = new AiReviewCommand(
                $workflow,
                $serializer,
            );

            $application = new Application();

            $application->addCommand($command);

            $commandTester = new CommandTester(
                $application->find('ai:review')
            );

            $exitCode = $commandTester->execute([
                'from' => 'FROM_SHA',
                'to' => 'TO_SHA',
                '--output' => $outputFile,
            ]);

            self::assertSame(
                0,
                $exitCode,
            );

            self::assertFileExists($outputFile);

            $json = file_get_contents($outputFile);

            self::assertIsString($json);

            $data = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            self::assertSame(
                'TO_SHA',
                $data['commit_sha'],
            );

            self::assertSame(
                [],
                $data['reviews'],
            );
        } finally {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }
    }

    public function testItPrintsNoFindingsForFilesThatReviewCleanly(): void
    {
        $workflow = $this->createMock(
            CodeReviewWorkflowInterface::class
        );

        $workflow
            ->expects(self::once())
            ->method('reviewChanges')
            ->with('FROM_SHA', 'TO_SHA')
            ->willReturn(new ReviewBatch([
                'fixtures/clean.php' => new ReviewResult([]),
            ]));

        $serializer = new ReviewResultSerializer();
        $outputFile = tempnam(sys_get_temp_dir(), 'ai-review-');

        self::assertNotFalse($outputFile);

        try {
            $command = new AiReviewCommand($workflow, $serializer);
            $application = new Application();
            $application->addCommand($command);

            $commandTester = new CommandTester($application->find('ai:review'));

            $exitCode = $commandTester->execute([
                'from' => 'FROM_SHA',
                'to' => 'TO_SHA',
                '--output' => $outputFile,
            ]);

            self::assertSame(0, $exitCode);
            self::assertStringContainsString('No findings.', $commandTester->getDisplay());
            self::assertStringContainsString('Review result written to:', $commandTester->getDisplay());

            $data = json_decode((string) file_get_contents($outputFile), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('TO_SHA', $data['commit_sha']);
            self::assertSame([], $data['reviews']['fixtures/clean.php']['findings']);
        } finally {
            if (is_file($outputFile)) {
                unlink($outputFile);
            }
        }
    }
}