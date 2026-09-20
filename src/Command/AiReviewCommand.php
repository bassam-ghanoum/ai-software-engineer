<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\Review\ReviewResultSerializer;
use App\AI\Workflow\CodeReviewWorkflowInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ai:review',
    description: 'Review changed PHP files using the AI code review agent.',
)]
final class AiReviewCommand extends Command
{
    public function __construct(
        private readonly CodeReviewWorkflowInterface $workflow,
        private readonly ReviewResultSerializer $serializer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'from',
            InputArgument::REQUIRED,
            'Git revision to compare from.',
        );

        $this->addArgument(
            'to',
            InputArgument::REQUIRED,
            'Git revision to compare to.',
        );

        $this->addOption(
            'output',
            null,
            InputOption::VALUE_REQUIRED,
            'Write the serialized review result to this JSON file.',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $from = (string) $input->getArgument('from');
        $to = (string) $input->getArgument('to');
        $outputPath = $input->getOption('output');

        $output->writeln(
            sprintf(
                '<info>Reviewing PHP changes: %s → %s</info>',
                $from,
                $to,
            )
        );

        $results = $this->workflow->reviewChanges(
            $from,
            $to,
        );

        if ($results === []) {
            $output->writeln(
                '<comment>No changed PHP files found.</comment>'
            );

            if ($outputPath !== null) {
                $serialized = $this->serializer->serialize(
                    [],
                    $to,
                );

                $this->writeReviewResult(
                    (string) $outputPath,
                    $serialized,
                );

                $output->writeln(sprintf(
                    '<info>Review result written to: %s</info>',
                    $outputPath,
                ));
            }

            return Command::SUCCESS;
        }

        foreach ($results as $path => $result) {
            $output->writeln('');
            $output->writeln(
                sprintf(
                    '<info>File: %s</info>',
                    $path,
                )
            );

            if (!$result->hasFindings()) {
                $output->writeln('<info>No findings.</info>');

                continue;
            }

            foreach ($result->getFindings() as $finding) {
                $output->writeln(
                    sprintf(
                        '<error>[%s] [%s] Line %d</error> %s',
                        strtoupper($finding->getSeverity()),
                        $finding->getCategory(),
                        $finding->getLine(),
                        $finding->getMessage(),
                    )
                );

                $output->writeln(
                    sprintf(
                        '  Suggestion: %s',
                        $finding->getSuggestion(),
                    )
                );
            }
        }

        if ($outputPath !== null) {
            $serialized = $this->serializer->serialize(
                $results,
                $to,
            );

            $this->writeReviewResult(
                (string) $outputPath,
                $serialized,
            );

            $output->writeln('');
            $output->writeln(sprintf(
                '<info>Review result written to: %s</info>',
                $outputPath,
            ));
        }

        return Command::SUCCESS;
    }

    private function writeReviewResult(
        string $outputPath,
        string $content,
    ): void {
        $directory = dirname($outputPath);

        if (
            $directory !== '.'
            && !is_dir($directory)
            && !mkdir($directory, 0777, true)
            && !is_dir($directory)
        ) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to create review result directory: %s',
                    $directory,
                )
            );
        }

        $bytes = file_put_contents(
            $outputPath,
            $content,
        );

        if ($bytes === false) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to write review result to: %s',
                    $outputPath,
                )
            );
        }
    }
}