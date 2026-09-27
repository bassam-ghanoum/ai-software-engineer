<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\Review\ReviewArtifactValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ai:review:validate-result',
    description: 'Validate a persisted review result before applying fixes.',
)]
final class ValidateReviewResultCommand extends Command
{
    public function __construct(
        private readonly ReviewArtifactValidator $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'file',
            InputArgument::REQUIRED,
            'Review result JSON file.',
        );

        $this->addOption(
            'commit-sha',
            null,
            InputOption::VALUE_REQUIRED,
            'Expected pull request HEAD SHA.',
        );

        $this->addOption(
            'base-sha',
            null,
            InputOption::VALUE_REQUIRED,
            'Expected pull request base SHA.',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $file = (string) $input->getArgument('file');
        $commitShaOption = $input->getOption('commit-sha');
        $expectedCommitSha = $commitShaOption !== null ? (string) $commitShaOption : '';
        $baseShaOption = $input->getOption('base-sha');
        $expectedBaseSha = $baseShaOption !== null ? (string) $baseShaOption : '';
        try {
            $findingsCount = $this->validator->validate(
                $file,
                $expectedCommitSha,
                $expectedBaseSha,
            );
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        $output->writeln('<info>Review result is valid.</info>');
        $output->writeln(sprintf(
            '<info>Findings: %d</info>',
            $findingsCount,
        ));

        return Command::SUCCESS;
    }
}
