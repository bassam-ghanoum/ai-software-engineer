<?php

namespace App\AI\LLM;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiLlm implements LlmInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model
    ) {
    }

    public function generate(string $prompt): string
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                'https://api.openai.com/v1/responses',
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'model' => $this->model,
                        'input' => $prompt,
                    ],
                ]
            );

            $data = $response->toArray();

            return $data['output'][0]['content'][0]['text'] ?? '';
        } catch (ClientException $exception) {
            $statusCode = $exception->getResponse()->getStatusCode();

            if ($statusCode === 429) {
                throw new \RuntimeException(
                    'OpenAI API quota or rate limit exceeded on ' . $this->model . '.',
                    0,
                    $exception
                );
            }

            throw new \RuntimeException(
                'OpenAI API request failed with HTTP status ' . $statusCode . '.',
                0,
                $exception
            );
        }
    }
}