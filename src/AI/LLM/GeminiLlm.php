<?php

declare(strict_types=1);

namespace App\AI\LLM;

use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiLlm implements LlmInterface
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $retryDelay,
    ) {
    }

    public function generate(string $prompt): string
    {
        $data = $this->request([
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
        ]);

        return $this->extractText($data);
    }

    public function generateJson(string $prompt): string
    {
        $data = $this->request([
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $prompt,
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'responseMimeType' => 'application/json',
            ],
        ]);

        return $this->extractText($data);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function request(array $payload): array
    {
        $url = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $this->model,
        );

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->httpClient->request(
                    'POST',
                    $url,
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'x-goog-api-key' => $this->apiKey,
                        ],
                        'timeout' => 120,
                        'json' => $payload,
                    ],
                );

                $statusCode = $response->getStatusCode();

                if ($this->shouldRetry($statusCode) && $attempt < self::MAX_RETRIES) {
                    $this->waitBeforeRetry($attempt);

                    continue;
                }

                $data = $response->toArray(false);

                if ($statusCode >= 400) {
                    throw new RuntimeException(sprintf(
                        'Gemini API request failed with HTTP %d: %s',
                        $statusCode,
                        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    ));
                }

                return $data;
            } catch (TransportExceptionInterface $exception) {
                if ($attempt >= self::MAX_RETRIES) {
                    throw $exception;
                }

                $this->waitBeforeRetry($attempt);
            }
        }

        throw new RuntimeException('Gemini API request failed after all retry attempts.');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function extractText(array $data): string
    {
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (is_string($text) && $text !== '') {
            return $text;
        }

        throw new RuntimeException(sprintf(
            'Gemini returned no text content. Response: %s',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ));
    }

    private function shouldRetry(int $statusCode): bool
    {
        return in_array($statusCode, [429, 503], true);
    }

    private function waitBeforeRetry(int $attempt): void
    {
        if ($this->retryDelay <= 0) {
            return;
        }

        sleep($this->retryDelay * (2 ** $attempt));
    }
}