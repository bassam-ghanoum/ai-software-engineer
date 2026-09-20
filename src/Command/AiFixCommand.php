<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\Workflow\CodeReviewWorkflowInterface;
use App\AI\Workflow\FixWorkflow;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Helper\QuestionHelper;

#[AsCommand(
    name: 'ai:fix',
    description: 'Review changed PHP files and apply fixes after developer approval.',
)]
final class AiFixCommand extends Command
{
    public function __construct(
        private readonly CodeReviewWorkflowInterface $codeReviewWorkflow,
        private readonly FixWorkflow $fixWorkflow,
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
            'Skip interactive approval because the developer already approved the GitHub pull request.',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $from = (string) $input->getArgument('from');
        $to = (string) $input->getArgument('to');
        $approved = $input->getOption('approved');

        $output->writeln(sprintf(
            '<info>Reviewing changes from %s to %s...</info>',
            $from,
            $to,
        ));

        $reviews = $this->codeReviewWorkflow->reviewChanges(
            $from,
            $to,
        );

        if ($reviews === []) {
            $output->writeln('<comment>No changed PHP files found.</comment>');

            return Command::SUCCESS;
        }

        $hasFindings = false;

        foreach ($reviews as $filePath => $reviewResult) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<info>File: %s</info>',
                $filePath,
            ));

            if (!$reviewResult->hasFindings()) {
                $output->writeln('No findings.');

                continue;
            }

            $hasFindings = true;

            foreach ($reviewResult->getFindings() as $finding) {
                $output->writeln(sprintf(
                    '  [%s] %s: %s',
                    strtoupper($finding->getSeverity()),
                    $finding->getCategory(),
                    $finding->getMessage(),
                ));

                $output->writeln(sprintf(
                    '  Suggestion: %s',
                    $finding->getSuggestion(),
                ));
            }
        }

        if (!$hasFindings) {
            $output->writeln('');
            $output->writeln('<info>No issues found. Nothing to fix.</info>');

            return Command::SUCCESS;
        }

        if (!$approved) {
            $helper = $this->getHelper('question');

            if (!$helper instanceof QuestionHelper) {
                throw new \RuntimeException('Question helper is not available.');
            }

            $question = new ConfirmationQuestion(
                PHP_EOL . 'Apply these fixes? [y/N] ',
                false,
            );

            $approved = $helper->ask($input, $output, $question);
        }

        if (!$approved) {
            $output->writeln(
                '<comment>Fixes were not approved. No files were modified.</comment>'
            );

            return Command::SUCCESS;
        }

        $output->writeln('');
        $output->writeln('<info>Applying fixes one file at a time...</info>');

        foreach ($reviews as $filePath => $reviewResult) {
            if (!$reviewResult->hasFindings()) {
                continue;
            }

            $output->writeln(sprintf(
                '<info>Fixing: %s</info>',
                $filePath,
            ));
        }

        $result = $this->fixWorkflow->fix(
            $reviews,
            true,
        );

        if (!$result->hasChanges()) {
            $output->writeln('<comment>No files were changed.</comment>');

            return Command::SUCCESS;
        }

        $output->writeln('');

        foreach ($result->getFixedFiles() as $filePath => $fixedSource) {
            $output->writeln(sprintf(
                '<info>Fixed: %s</info>',
                $filePath,
            ));
        }

        $output->writeln('');
        $output->writeln(sprintf(
            '<info>Fixed %d file(s).</info>',
            $result->count(),
        ));

        return Command::SUCCESS;
    }
}
