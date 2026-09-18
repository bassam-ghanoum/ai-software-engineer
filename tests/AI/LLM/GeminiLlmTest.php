<?php

declare(strict_types=1);

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
            ->method('getStatusCode')
            ->willReturn(200);

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
                        $options['headers']['Content-Type'] === 'application/json'
                        && $options['headers']['x-goog-api-key'] === 'test-api-key'
                        && $options['timeout'] === 120
                        && $options['json']['contents'][0]['parts'][0]['text'] === 'Test prompt';
                })
            )
            ->willReturn($response);

        $llm = new GeminiLlm(
            $httpClient,
            'test-api-key',
            'gemini-2.5-flash',
            0,
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
            ->method('getStatusCode')
            ->willReturn(200);

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
                        $options['headers']['Content-Type'] === 'application/json'
                        && $options['headers']['x-goog-api-key'] === 'test-api-key'
                        && $options['timeout'] === 120
                        && $options['json']['generationConfig']['responseMimeType'] === 'application/json';
                })
            )
            ->willReturn($response);

        $llm = new GeminiLlm(
            $httpClient,
            'test-api-key',
            'gemini-2.5-flash',
            0,
        );

        self::assertSame(
            '{"findings":[]}',
            $llm->generateJson('Test prompt')
        );
    }

    public function testGenerateRetriesAfter429AndSucceeds(): void
    {
        $rateLimitedResponse = $this->createStub(ResponseInterface::class);

        $rateLimitedResponse
            ->method('getStatusCode')
            ->willReturn(429);

        $successfulResponse = $this->createStub(ResponseInterface::class);

        $successfulResponse
            ->method('getStatusCode')
            ->willReturn(200);

        $successfulResponse
            ->method('toArray')
            ->willReturn([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'Success after retry',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects(self::exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $rateLimitedResponse,
                $successfulResponse
            );

        $llm = new GeminiLlm(
            $httpClient,
            'test-api-key',
            'gemini-2.5-flash',
            0,
        );

        self::assertSame(
            'Success after retry',
            $llm->generate('Test prompt')
        );
    }

    public function testGenerateRetriesAfter503AndSucceeds(): void
    {
        $unavailableResponse = $this->createStub(ResponseInterface::class);

        $unavailableResponse
            ->method('getStatusCode')
            ->willReturn(503);

        $successfulResponse = $this->createStub(ResponseInterface::class);

        $successfulResponse
            ->method('getStatusCode')
            ->willReturn(200);

        $successfulResponse
            ->method('toArray')
            ->willReturn([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => 'Success after retry',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects(self::exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                $unavailableResponse,
                $successfulResponse
            );

        $llm = new GeminiLlm(
            $httpClient,
            'test-api-key',
            'gemini-2.5-flash',
            0,
        );

        self::assertSame(
            'Success after retry',
            $llm->generate('Test prompt')
        );
    }

    public function testGenerateDoesNotRetryOn400(): void
    {
        $badRequestResponse = $this->createStub(ResponseInterface::class);

        $badRequestResponse
            ->method('getStatusCode')
            ->willReturn(400);

        $badRequestResponse
            ->method('toArray')
            ->willReturn([
                'error' => [
                    'message' => 'Bad request',
                ],
            ]);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects(self::once())
            ->method('request')
            ->willReturn($badRequestResponse);

        $llm = new GeminiLlm(
            $httpClient,
            'test-api-key',
            'gemini-2.5-flash',
            0,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gemini API request failed with HTTP 400');

        $llm->generate('Test prompt');
    }

    public function testGenerateRetriesMaximumNumberOfTimes(): void
    {
        $rateLimitedResponse = $this->createStub(ResponseInterface::class);

        $rateLimitedResponse
            ->method('getStatusCode')
            ->willReturn(429);

        $rateLimitedResponse
            ->method('toArray')
            ->willReturn([]);

        $httpClient = $this->createMock(HttpClientInterface::class);

        $httpClient
            ->expects(self::exactly(4))
            ->method('request')
            ->willReturn($rateLimitedResponse);

        $llm = new GeminiLlm(
            $httpClient,
            'test-api-key',
            'gemini-2.5-flash',
            0,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Gemini API request failed with HTTP 429');

        $llm->generate('Test prompt');
    }
}