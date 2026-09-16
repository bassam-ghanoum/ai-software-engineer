<?php

declare(strict_types=1);

namespace App\AI\LLM;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiLlm implements LlmInterface
{
    private const MAX_RETRIES = 3;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model,
        private readonly int $retryDelay = 1,
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

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
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

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
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

                return $response->toArray(false);
            } catch (TransportExceptionInterface $exception) {
                if ($attempt >= self::MAX_RETRIES) {
                    throw $exception;
                }

                $this->waitBeforeRetry($attempt);
            }
        }

        return [];
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
