<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\Git\GitInterface;
use App\AI\Review\ReviewResultSerializer;
use App\AI\Workflow\CodeReviewWorkflowInterface;
use App\AI\Workflow\FixWorkflowInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'ai:fix',
    description: 'Apply approved AI code review fixes.',
)]
final class AiFixCommand extends Command
{
    public function __construct(
        private readonly CodeReviewWorkflowInterface $codeReviewWorkflow,
        private readonly ReviewResultSerializer $serializer,
        private readonly FixWorkflowInterface $fixWorkflow,
        private readonly GitInterface $git,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'from',
            InputArgument::REQUIRED,
            'The starting Git revision.',
        );

        $this->addArgument(
            'to',
            InputArgument::REQUIRED,
            'The ending Git revision.',
        );

        $this->addOption(
            'approved',
            null,
            InputOption::VALUE_NONE,
            'Skip interactive approval because the developer already approved the pull request.',
        );

        $this->addOption(
            'review-file',
            null,
            InputOption::VALUE_REQUIRED,
            'Read the previously generated ReviewResult from this JSON file.',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $from = (string) $input->getArgument('from');
        $to = (string) $input->getArgument('to');
        $approved = (bool) $input->getOption('approved');
        $reviewFile = $input->getOption('review-file');

        if ($reviewFile !== null) {
            $reviews = $this->loadReviewsFromFile(
                (string) $reviewFile,
                $to,
            );

            $output->writeln(
                sprintf(
                    '<info>Using persisted AI review from: %s</info>',
                    $reviewFile,
                )
            );
        } else {
            $output->writeln(
                sprintf(
                    '<info>Reviewing changes from %s to %s...</info>',
                    $from,
                    $to,
                )
            );

            $reviews = $this->codeReviewWorkflow->reviewChanges(
                $from,
                $to,
            );
        }

        if ($reviews === []) {
            $output->writeln(
                '<comment>No changed PHP files found.</comment>'
            );

            return Command::SUCCESS;
        }

        $hasFindings = false;

        foreach ($reviews as $filePath => $reviewResult) {
            $output->writeln('');
            $output->writeln(
                sprintf(
                    '<info>File: %s</info>',
                    $filePath,
                )
            );

            if (!$reviewResult->hasFindings()) {
                $output->writeln('No findings.');

                continue;
            }

            $hasFindings = true;

            foreach ($reviewResult->getFindings() as $finding) {
                $output->writeln(
                    sprintf(
                        '  [%s] [%s] Line %d: %s',
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

        if (!$hasFindings) {
            $output->writeln('');
            $output->writeln(
                '<info>No issues found. Nothing to fix.</info>'
            );

            return Command::SUCCESS;
        }

        if (!$approved) {
            $helper = $this->getHelper('question');

            if (!$helper instanceof QuestionHelper) {
                throw new \RuntimeException(
                    'Question helper is not available.'
                );
            }

            $question = new ConfirmationQuestion(
                PHP_EOL . 'Apply these fixes? [y/N] ',
                false,
            );

            $approved = $helper->ask(
                $input,
                $output,
                $question,
            );
        }

        if (!$approved) {
            $output->writeln(
                '<comment>Fixes were not approved. No files were modified.</comment>'
            );

            return Command::SUCCESS;
        }

        $this->git->assertWorkingTreeMatchesRevision($to);

        $output->writeln(sprintf(
            '<info>Working tree matches target revision %s.</info>',
            OutputFormatter::escape($to),
        ));

        $output->writeln('');
        $output->writeln(
            '<info>Applying fixes one file at a time...</info>'
        );

        foreach ($reviews as $filePath => $reviewResult) {
            if (!$reviewResult->hasFindings()) {
                continue;
            }

            $output->writeln(
                sprintf(
                    '<info>Fixing: %s</info>',
                    $filePath,
                )
            );
        }

        $result = $this->fixWorkflow->fix(
            $reviews,
            true,
        );

        if (!$result->hasChanges()) {
            $output->writeln(
                '<comment>No files were changed.</comment>'
            );

            return Command::SUCCESS;
        }

        $output->writeln('');

        foreach ($result->getFixedFiles() as $filePath => $fixedSource) {
            $output->writeln(
                sprintf(
                    '<info>Fixed: %s</info>',
                    $filePath,
                )
            );
        }

        $output->writeln('');
        $output->writeln(
            sprintf(
                '<info>Fixed %d file(s).</info>',
                $result->count(),
            )
        );

        return Command::SUCCESS;
    }

    /**
     * @return array<string, \App\AI\Review\ReviewResult>
     */
    private function loadReviewsFromFile(
        string $reviewFile,
        string $expectedCommitSha,
    ): array {
        if (!is_file($reviewFile)) {
            throw new \RuntimeException(
                sprintf(
                    'Review result file does not exist: %s',
                    $reviewFile,
                )
            );
        }

        $json = file_get_contents($reviewFile);

        if ($json === false) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to read review result file: %s',
                    $reviewFile,
                )
            );
        }

        $result = $this->serializer->deserialize($json);

        $reviewCommitSha = $result['commit_sha'];

        if ($reviewCommitSha !== $expectedCommitSha) {
            throw new \RuntimeException(
                sprintf(
                    'Review result belongs to commit "%s", but the current fix target is "%s".',
                    $reviewCommitSha,
                    $expectedCommitSha,
                )
            );
        }

        return $result['reviews'];
    }
}
