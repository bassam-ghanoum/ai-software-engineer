<?php

declare(strict_types=1);

namespace App\AI\Agent;

use RuntimeException;

final class PromptTemplateLoader
{
    public function __construct(
        private readonly string $promptsDirectory,
    ) {
    }

    /**
     * @param array<string, string> $replacements
     */
    public function render(string $templateName, array $replacements): string
    {
        $templatePath = $this->promptsDirectory
            . DIRECTORY_SEPARATOR
            . basename($templateName);

        if (!is_file($templatePath) || !is_readable($templatePath)) {
            throw new RuntimeException(
                sprintf('Prompt template "%s" is not readable.', $templateName),
            );
        }

        $template = file_get_contents($templatePath);

        if ($template === false) {
            throw new RuntimeException(
                sprintf('Prompt template "%s" could not be read.', $templateName),
            );
        }

        return strtr($template, $replacements);
    }
}
