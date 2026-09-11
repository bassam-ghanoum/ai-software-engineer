<?php

namespace App\AI\LLM;

use Symfony\Contracts\HttpClient\HttpClientInterface;

final class GeminiLlm implements LlmInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model
    ) {
    }

    public function generate(string $prompt): string
    {
        $response = $this->httpClient->request(
            'POST',
            sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
                $this->model
            ),
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ],
                'timeout' => 120,
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                [
                                    'text' => $prompt,
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );

        $data = $response->toArray();

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    public function generateJson(string $prompt): string
    {
        $response = $this->httpClient->request(
            'POST',
            sprintf(
                'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
                $this->model
            ),
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ],
                'timeout' => 120,
                'json' => [
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
                ],
            ]
        );

        $data = $response->toArray();

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }
}
