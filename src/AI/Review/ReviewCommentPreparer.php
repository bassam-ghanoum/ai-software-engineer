<?php

declare(strict_types=1);

namespace App\AI\Review;

final class ReviewCommentPreparer
{
    public function __construct(
        private readonly ReviewResultSerializer $serializer,
        private readonly ChangedLinesParser $changedLinesParser,
    ) {
    }

    /**
     * @return array{
        *     changed_lines: array<string, array<int, bool>>,
     *     inline: array<int, array<string, mixed>>,
     *     general: array<int, array<string, mixed>>
     * }
     */
    public function prepare(
        string $reviewJson,
        string $changedDiff,
    ): array {
        $result = $this->serializer->deserialize($reviewJson);
        $changedLines = $this->changedLinesParser->parse($changedDiff);
        $inline = [];
        $general = [];

        foreach ($result['reviews'] as $file => $review) {
            $path = ltrim($file, '/');

            foreach ($review->getFindings() as $finding) {
                $fingerprint = $this->fingerprint(
                    $path,
                    $finding->getCategory(),
                    $finding->getMessage(),
                    $finding->getSuggestion(),
                );

                $comment = [
                    'fingerprint' => $fingerprint,
                    'path' => $path,
                    'line' => $finding->getLine(),
                    'side' => 'RIGHT',
                    'body' => $this->buildBody(
                        $fingerprint,
                        $finding,
                    ),
                ];

                if (
                    isset($changedLines[$path])
                    && isset($changedLines[$path][$finding->getLine()])
                ) {
                    $inline[] = $comment;
                    continue;
                }

                $general[] = $comment;
            }
        }

        return [
            'changed_lines' => $changedLines,
            'inline' => $inline,
            'general' => $general,
        ];
    }

    private function fingerprint(
        string $file,
        string $category,
        string $message,
        string $suggestion,
    ): string {
        $payload = implode('|', [
            $this->normalize($file),
            $this->normalize($category),
            $this->normalize($message),
            $this->normalize($suggestion),
        ]);

        return hash('sha256', $payload);
    }

    private function normalize(string $value): string
    {
        $normalized = preg_replace('/\s+/', ' ', strtolower(trim($value)));

        return $normalized ?? strtolower(trim($value));
    }

    private function buildBody(
        string $fingerprint,
        ReviewFinding $finding,
    ): string {
        $body = "### 🤖 AI Code Review\n\n";
        $body .= "<!-- ai-code-review-fingerprint:" . $fingerprint . " -->\n\n";
        $body .= sprintf(
            "**%s** · `%s`\n\n",
            strtoupper($finding->getSeverity()),
            $finding->getCategory(),
        );
        $body .= $finding->getMessage();

        if ($finding->getSuggestion() !== '') {
            $body .= "\n\n**Suggestion:** " . $finding->getSuggestion();
        }

        return $body;
    }
}
