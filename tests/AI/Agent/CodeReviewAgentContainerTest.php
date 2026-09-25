<?php

namespace App\Tests\AI\Agent;

use App\AI\Agent\CodeReviewAgent;
use App\AI\LLM\GeminiLlm;
use App\AI\LLM\LlmInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CodeReviewAgentContainerTest extends KernelTestCase
{
    public function testCodeReviewAgentIsRegisteredInContainer(): void
    {
        self::bootKernel([
            'environment' => 'test',
            'debug' => true,
        ]);

        $container = self::getContainer();

        $agent = $container->get(CodeReviewAgent::class);

        self::assertInstanceOf(
            CodeReviewAgent::class,
            $agent
        );
    }

    public function testCodeReviewAgentInterfaceUsesCodeReviewAgentImplementation(): void
    {
        self::bootKernel([
            'environment' => 'test',
            'debug' => true,
        ]);

        $agent = self::getContainer()->get(
            \App\AI\Agent\CodeReviewAgentInterface::class
        );

        self::assertInstanceOf(CodeReviewAgent::class, $agent);
    }

    public function testLlmInterfaceUsesGeminiImplementation(): void
    {
        self::bootKernel([
            'environment' => 'test',
            'debug' => true,
        ]);

        $container = self::getContainer();

        $llm = $container->get(LlmInterface::class);

        self::assertInstanceOf(
            GeminiLlm::class,
            $llm
        );
    }
}