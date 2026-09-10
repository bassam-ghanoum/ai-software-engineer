<?php

namespace App\Tests\AI\LLM;

use App\AI\LLM\GeminiLlm;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class GeminiLlmTest extends TestCase
{
    public function testGenerateReturnsTextFromGeminiResponse(): void
    {
        $response = $this->createStub(ResponseInterface::class);

        $response
            ->method('toArray')
            ->willReturn([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'Hello from Gemini',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
                self::callback(function (array $options): bool {
                    return
                        $options['headers']['Content-Type']
                            === 'application/json'
                        && $options['headers']['x-goog-api-key']
                            === 'test-api-key'
                        && $options['json']['contents'][0]['parts'][0]['text']
                            === 'Test prompt';
                })
            )
            ->willReturn($response);

        $llm = new GeminiLlm(
            $httpClient,
            'test-api-key',
            'gemini-2.5-flash'
        );

        self::assertSame(
            'Hello from Gemini',
            $llm->generate('Test prompt')
        );
    }

    public function testGenerateJsonReturnsJsonFromGeminiResponse(): void
    {
        $response = $this->createStub(ResponseInterface::class);

        $response
            ->method('toArray')
            ->willReturn([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => '{"findings":[]}',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent',
                self::callback(function (array $options): bool {
                    return
                        $options['headers']['Content-Type']
                            === 'application/json'
                        && $options['headers']['x-goog-api-key']
                            === 'test-api-key'
                        && $options['json']['generationConfig']['responseMimeType']
                            === 'application/json';
                })
            )
            ->willReturn($response);

        $llm = new GeminiLlm(
            $httpClient,
            'test-api-key',
            'gemini-2.5-flash'
        );

        self::assertSame(
            '{"findings":[]}',
            $llm->generateJson('Test prompt')
        );
    }
}