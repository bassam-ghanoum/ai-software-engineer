<?php

declare(strict_types=1);

namespace App\Command;

use App\AI\Review\ReviewCommentPreparer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'ai:review:prepare-comments',
    description: 'Prepare GitHub review comment payloads from a review result.',
)]
final class PrepareReviewCommentsCommand extends Command
{
    public function __construct(
        private readonly ReviewCommentPreparer $preparer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'review-file',
            InputArgument::REQUIRED,
            'Review result JSON file.',
        );

        $this->addArgument(
            'diff-file',
            InputArgument::REQUIRED,
            'Unified Git diff containing changed PHP lines.',
        );

        $this->addOption(
            'changed-lines-output',
            null,
            InputOption::VALUE_REQUIRED,
            'Output file for the changed-line map.',
            'changed-lines.json',
        );

        $this->addOption(
            'inline-output',
            null,
            InputOption::VALUE_REQUIRED,
            'Output file for inline review comments.',
            'review-inline-comments.json',
        );

        $this->addOption(
            'general-output',
            null,
            InputOption::VALUE_REQUIRED,
            'Output file for general review comments.',
            'review-general-comments.json',
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $reviewFile = (string) $input->getArgument('review-file');
        $diffFile = (string) $input->getArgument('diff-file');
        $reviewJson = $this->readFile($reviewFile);
        $diff = $this->readFile($diffFile);
        $prepared = $this->preparer->prepare($reviewJson, $diff);

        $this->writeJson(
            (string) $input->getOption('changed-lines-output'),
            $prepared->changedLines,
        );
        $this->writeJson(
            (string) $input->getOption('inline-output'),
            $prepared->inline,
        );
        $this->writeJson(
            (string) $input->getOption('general-output'),
            $prepared->general,
        );

        $output->writeln(sprintf(
            '<info>Prepared %d inline finding(s).</info>',
            count($prepared->inline),
        ));
        $output->writeln(sprintf(
            '<info>Prepared %d general finding(s).</info>',
            count($prepared->general),
        ));

        return Command::SUCCESS;
    }

    private function readFile(string $file): string
    {
        if (!is_file($file) || !is_readable($file)) {
            throw new \RuntimeException(
                sprintf('File cannot be read: %s', $file),
            );
        }

        $content = file_get_contents($file);

        if ($content === false) {
            throw new \RuntimeException(
                sprintf('Failed to read file: %s', $file),
            );
        }

        return $content;
    }

    /**
     * @param mixed $data
     */
    private function writeJson(string $file, mixed $data): void
    {
        $directory = dirname($file);

        if (
            $directory !== '.'
            && !is_dir($directory)
            && !mkdir($directory, 0777, true)
            && !is_dir($directory)
        ) {
            throw new \RuntimeException(
                sprintf('Failed to create output directory: %s', $directory),
            );
        }

        try {
            $content = json_encode(
                $data,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR,
            ) . PHP_EOL;
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                sprintf('Failed to encode output file: %s', $file),
                0,
                $exception,
            );
        }

        if (file_put_contents($file, $content) !== strlen($content)) {
            throw new \RuntimeException(
                sprintf('Failed to write output file: %s', $file),
            );
        }
    }
}
