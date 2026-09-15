<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\LLM\GeminiLlm;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ai:test-gemini',
    description: 'Test the Gemini LLM directly.',
)]
final class TestGeminiCommand extends Command
{
    public function __construct(
        private readonly GeminiLlm $llm,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $output->writeln('Testing Gemini...');

        try {
            $response = $this->llm->generate(
                'Reply with exactly: OK'
            );

            $output->writeln('');
            $output->writeln($response);

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<error>%s</error>',
                $exception->getMessage(),
            ));

            return Command::FAILURE;
        }
    }
}