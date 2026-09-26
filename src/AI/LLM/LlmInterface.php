<?php

declare(strict_types=1);

namespace App\AI\LLM;

interface LlmInterface
{
    public function generate(string $prompt): string;

    public function generateJson(string $prompt): string;
}
