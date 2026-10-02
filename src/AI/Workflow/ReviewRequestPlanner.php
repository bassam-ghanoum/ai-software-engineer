<?php

declare(strict_types=1);

namespace App\AI\Workflow;

use App\AI\Agent\ReviewUnit;
use App\AI\DTO\Git\ChangedPhpFiles;

/** Builds bounded requests, batching small files and splitting large ones. */
final class ReviewRequestPlanner
{
    private const REQUEST_TOKEN_BUDGET = 16000;
    private const MAX_UNIT_CHARACTERS = 24000;
    private const ESTIMATED_PROMPT_OVERHEAD = 1500;

    /**
     * @return list<list<ReviewUnit>>
     */
    public function plan(ChangedPhpFiles $files): array
    {
        $batches = [];
        $currentBatch = [];
        $currentEstimate = self::ESTIMATED_PROMPT_OVERHEAD;
        $unitNumber = 1;

        foreach ($files as $filePath => $source) {
            foreach ($this->splitFile($filePath, $source, $unitNumber) as $unit) {
                // JSON escaping and the prompt instructions add tokens beyond the raw source.
                $unitEstimate = (int) ceil(strlen($unit->code) / 2) + 150;

                if (
                    $currentBatch !== []
                    && $currentEstimate + $unitEstimate > self::REQUEST_TOKEN_BUDGET
                ) {
                    $batches[] = $currentBatch;
                    $currentBatch = [];
                    $currentEstimate = self::ESTIMATED_PROMPT_OVERHEAD;
                }

                $currentBatch[] = $unit;
                $currentEstimate += $unitEstimate;
                $unitNumber++;
            }
        }

        if ($currentBatch !== []) {
            $batches[] = $currentBatch;
        }

        return $batches;
    }

    /** @return list<ReviewUnit> */
    private function splitFile(string $filePath, string $source, int $firstUnitNumber): array
    {
        $lines = preg_split('/\R/', $source);

        if ($lines === false || $lines === []) {
            throw new \RuntimeException(sprintf(
                'Unable to split changed file "%s".',
                $filePath,
            ));
        }

        if ($source === '') {
            throw new \RuntimeException(sprintf(
                'Changed file "%s" is empty and cannot be reviewed.',
                $filePath,
            ));
        }

        $units = [];
        $chunk = [];
        $chunkLength = 0;
        $chunkStartLine = 1;

        foreach ($lines as $index => $line) {
            $lineLength = strlen($line) + 1;

            if ($lineLength > self::MAX_UNIT_CHARACTERS) {
                throw new \RuntimeException(sprintf(
                    'Changed file "%s" contains a line too large to review within the request budget.',
                    $filePath,
                ));
            }

            if ($chunk !== [] && $chunkLength + $lineLength > self::MAX_UNIT_CHARACTERS) {
                $units[] = new ReviewUnit(
                    sprintf('unit-%d', $firstUnitNumber + count($units)),
                    $filePath,
                    $chunkStartLine,
                    implode("\n", $chunk),
                );
                $chunk = [];
                $chunkLength = 0;
                $chunkStartLine = $index + 1;
            }

            $chunk[] = $line;
            $chunkLength += $lineLength;
        }

        if ($chunk !== []) {
            $units[] = new ReviewUnit(
                sprintf('unit-%d', $firstUnitNumber + count($units)),
                $filePath,
                $chunkStartLine,
                implode("\n", $chunk),
            );
        }

        return $units;
    }
}
