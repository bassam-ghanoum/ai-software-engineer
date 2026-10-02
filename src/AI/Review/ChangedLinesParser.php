<?php

declare(strict_types=1);

namespace App\AI\Review;

use App\AI\DTO\Review\ChangedLineNumbers;
use App\AI\DTO\Review\ChangedLines;

final class ChangedLinesParser
{
    public function parse(string $diff): ChangedLines
    {
        $changedLines = [];
        $currentFile = null;
        $lines = preg_split('/\R/', $diff);

        if ($lines === false) {
            throw new \RuntimeException('Unable to split the Git diff.');
        }

        foreach ($lines as $line) {
            if (str_starts_with($line, '+++ b/')) {
                $currentFile = substr($line, 6);

                if ($currentFile === false || $currentFile === '') {
                    $currentFile = null;
                    continue;
                }

                $changedLines[$currentFile] ??= [];

                continue;
            }

            if ($currentFile === null) {
                continue;
            }

            if (
                !preg_match(
                    '/^@@\s+-\d+(?:,\d+)?\s+\+(\d+)(?:,(\d+))?\s+@@/',
                    $line,
                    $matches,
                )
            ) {
                continue;
            }

            $start = (int) $matches[1];
            $count = isset($matches[2]) ? (int) $matches[2] : 1;

            if ($count <= 0) {
                continue;
            }

            for (
                $lineNumber = $start;
                $lineNumber < $start + $count;
                $lineNumber++
            ) {
                $changedLines[$currentFile][$lineNumber] = true;
            }
        }

        foreach ($changedLines as $file => $lines) {
            ksort($lines, SORT_NUMERIC);
            $changedLines[$file] = $lines;
        }

        $files = [];

        foreach ($changedLines as $filePath => $lines) {
            $files[$filePath] = new ChangedLineNumbers($lines);
        }

        return new ChangedLines($files);
    }
}
