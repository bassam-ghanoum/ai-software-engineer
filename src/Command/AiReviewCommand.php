<?php

namespace App\Command;

use App\AI\Workflow\CodeReviewWorkflowInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ai:review',
    description: 'Review changed PHP files using the AI code review agent.'
)]
final class AiReviewCommand extends Command
{
    public function __construct(
        private readonly CodeReviewWorkflowInterface $workflow,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'from',
            InputArgument::REQUIRED,
            'Git revision to compare from.'
        );

        $this->addArgument(
            'to',
            InputArgument::REQUIRED,
            'Git revision to compare to.'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $from = (string) $input->getArgument('from');
        $to = (string) $input->getArgument('to');

        $output->writeln(
            sprintf(
                '<info>Reviewing PHP changes: %s → %s</info>',
                $from,
                $to
            )
        );

        $results = $this->workflow->reviewChanges($from, $to);

        if ($results === []) {
            $output->writeln('<comment>No changed PHP files found.</comment>');

            return Command::SUCCESS;
        }

        foreach ($results as $path => $result) {
            $output->writeln('');
            $output->writeln(sprintf('<info>File: %s</info>', $path));

            if (!$result->hasFindings()) {
                $output->writeln(
                    '<info>No findings.</info>'
                );

                continue;
            }

            foreach ($result->getFindings() as $finding) {
                $output->writeln(
                    sprintf(
                        '<error>[%s] [%s]</error> %s',
                        strtoupper($finding->getSeverity()),
                        $finding->getCategory(),
                        $finding->getMessage()
                    )
                );

                $output->writeln(
                    sprintf(
                        '  Suggestion: %s',
                        $finding->getSuggestion()
                    )
                );
            }
        }

        return Command::SUCCESS;
    }
}
