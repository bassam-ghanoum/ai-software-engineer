<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\Review\ReviewArtifactBinder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ai:review:bind-result',
    description: 'Bind a review result to a pull request commit and base.',
)]
final class BindReviewResultCommand extends Command
{
    public function __construct(
        private readonly ReviewArtifactBinder $binder,
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
            'Current pull request commit SHA.',
        );

        $this->addOption(
            'base-sha',
            null,
            InputOption::VALUE_REQUIRED,
            'Current pull request base SHA.',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $file = (string) $input->getArgument('file');
        $commitSha = (string) $input->getOption('commit-sha');
        $baseSha = (string) $input->getOption('base-sha');
        $findingsCount = $this->binder->bind(
            $file,
            $commitSha,
            $baseSha,
        );

        $output->writeln(sprintf(
            '<info>Review result bound to commit %s and base %s.</info>',
            $commitSha,
            $baseSha,
        ));
        $output->writeln(sprintf(
            '<info>Findings: %d</info>',
            $findingsCount,
        ));

        return Command::SUCCESS;
    }
}
