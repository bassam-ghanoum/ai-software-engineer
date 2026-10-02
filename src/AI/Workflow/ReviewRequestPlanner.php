<?php

declare(strict_types=1);

namespace App\AI\Workflow;

use App\AI\DTO\Agent\ReviewUnit;
use App\AI\DTO\Agent\ReviewUnitBatch;
use App\AI\DTO\Git\ChangedPhpFiles;
use App\AI\DTO\Workflow\ReviewRequestPlan;

/** Builds bounded requests, batching small files and splitting large ones. */
final class ReviewRequestPlanner
{
    private const REQUEST_TOKEN_BUDGET = 16000;
    private const MAX_UNIT_CHARACTERS = 24000;
    private const ESTIMATED_PROMPT_OVERHEAD = 1500;

    public function plan(ChangedPhpFiles $files): ReviewRequestPlan
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
                    $batches[] = new ReviewUnitBatch($currentBatch);
                    $currentBatch = [];
                    $currentEstimate = self::ESTIMATED_PROMPT_OVERHEAD;
                }

                $currentBatch[] = $unit;
                $currentEstimate += $unitEstimate;
                $unitNumber++;
            }
        }

        if ($currentBatch !== []) {
            $batches[] = new ReviewUnitBatch($currentBatch);
        }

        return new ReviewRequestPlan($batches);
    }

    private function splitFile(
        string $filePath,
        string $source,
        int $firstUnitNumber,
    ): ReviewUnitBatch
    {
        if ($source === '') {
            throw new \RuntimeException(sprintf(
                'Changed file "%s" is empty and cannot be reviewed.',
                $filePath,
            ));
        }

        $parts = preg_split('/(\R)/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false || $parts === []) {
            throw new \RuntimeException(sprintf(
                'Unable to split changed file "%s".',
                $filePath,
            ));
        }

        $lines = [];

        for ($index = 0; $index < count($parts); $index += 2) {
            $line = $parts[$index];

            if (isset($parts[$index + 1])) {
                $line .= $parts[$index + 1];
            }

            $lines[] = $line;
        }

        $units = [];
        $chunk = [];
        $chunkLength = 0;
        $chunkStartLine = 1;

        foreach ($lines as $index => $line) {
            $lineLength = strlen($line);

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
                    implode('', $chunk),
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
                implode('', $chunk),
            );
        }

        return new ReviewUnitBatch($units);
    }
}
