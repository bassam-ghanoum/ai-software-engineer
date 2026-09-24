<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\Review\ReviewArtifactFilter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ai:review:filter-resolved',
    description: 'Remove findings whose GitHub review threads are resolved.',
)]
final class FilterResolvedReviewCommand extends Command
{
    public function __construct(
        private readonly ReviewArtifactFilter $filter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('review-file', InputArgument::REQUIRED);
        $this->addArgument('fingerprints-file', InputArgument::REQUIRED);
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $reviewFile = (string) $input->getArgument('review-file');
        $fingerprintsFile = (string) $input->getArgument('fingerprints-file');

        $removed = $this->filter->filter(
            $reviewFile,
            $this->filter->readResolvedFingerprints($fingerprintsFile),
        );

        $output->writeln(sprintf(
            '<info>Resolved findings removed: %d</info>',
            $removed,
        ));

        return Command::SUCCESS;
    }
}