<?php

declare(strict_types=1);

namespace App\AI\Review;

use App\AI\DTO\Review\ChangedLineNumbers;
use App\AI\DTO\Review\ReviewComment;
use App\AI\DTO\Review\ReviewCommentCollection;
use App\AI\DTO\Review\ReviewCommentPreparationResult;
use RuntimeException;

final class ReviewCommentPreparer
{
    private const MAX_INLINE_LINE_DISTANCE = 3;

    public function __construct(
        private readonly ReviewResultSerializer $serializer,
        private readonly ChangedLinesParser $changedLinesParser,
    ) {
    }

    public function prepare(
        string $reviewJson,
        string $changedDiff,
    ): ReviewCommentPreparationResult {
        $result = $this->serializer->deserialize($reviewJson);

        $changedLines = $this->changedLinesParser->parse($changedDiff);
        $inline = [];
        $general = [];

        foreach ($result->reviews as $file => $review) {
            $path = ltrim($file, '/');
            $fileChangedLines = $changedLines->getFile($path)
                ?? new ChangedLineNumbers([]);

            foreach ($review->getFindings() as $finding) {
                $inlineLine = $fileChangedLines->contains($finding->getLine())
                    ? $finding->getLine()
                    : $fileChangedLines->nearestTo(
                        $finding->getLine(),
                        self::MAX_INLINE_LINE_DISTANCE,
                    );
                $fingerprint = $this->fingerprint(
                    $path,
                    $finding->getCategory(),
                    $finding->getMessage(),
                    $finding->getSuggestion(),
                );

                $body = $this->buildBody(
                    $fingerprint,
                    $path,
                    $finding->getLine(),
                    $finding,
                );

                if ($inlineLine !== null) {
                    $inline[] = new ReviewComment(
                        $fingerprint,
                        $path,
                        $inlineLine,
                        'RIGHT',
                        $body,
                    );
                    continue;
                }

                $body =
                    "Notice: This finding points to a line that was not changed "
                    . "in this pull request, so it is shown as a general comment.\n\n"
                    . $body;

                $general[] = new ReviewComment(
                    $fingerprint,
                    $path,
                    $finding->getLine(),
                    'RIGHT',
                    $body,
                );
            }
        }

        return new ReviewCommentPreparationResult(
            $changedLines,
            new ReviewCommentCollection($inline),
            new ReviewCommentCollection($general),
        );
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

        if ($normalized === null) {
            throw new RuntimeException('Unable to normalize review text.');
        }

        return $normalized;
    }

    private function buildBody(
        string $fingerprint,
        string $filePath,
        int $line,
        ReviewFinding $finding,
    ): string {
        $body = "### 🤖 AI Code Review\n\n";
        $body .= "<!-- ai-code-review-fingerprint:" . $fingerprint . " -->\n\n";
        $safeFilePath = str_replace(
            ['`', "\r", "\n"],
            ['\\`', ' ', ' '],
            $filePath,
        );
        $body .= sprintf("**File:** `%s` | **Line:** %d\n\n", $safeFilePath, $line);
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
