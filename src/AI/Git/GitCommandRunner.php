<?php

declare(strict_types=1);

namespace App\AI\Git;

use App\AI\Git\DTO\GitCommandResult;
use InvalidArgumentException;
use RuntimeException;

final class GitCommandRunner implements GitCommandRunnerInterface
{
    /**
     * @param list<string> $command
     */
    public function run(array $command): GitCommandResult
    {
        if ($command === [] || $command[0] === '') {
            throw new InvalidArgumentException('Command must include an executable.');
        }

        $process = proc_open(
            $command,
            [
                0 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start command.');
        }

        $output = '';
        $errorOutput = '';
        $streams = [
            'output' => $pipes[1],
            'errorOutput' => $pipes[2],
        ];
        $processClosed = false;

        try {
            while ($streams !== []) {
                $read = array_values($streams);
                $write = null;
                $except = null;
                $ready = stream_select($read, $write, $except, null);

                if ($ready === false) {
                    throw new RuntimeException('Unable to read command output.');
                }

                foreach ($read as $stream) {
                    $streamName = array_search($stream, $streams, true);
                    $chunk = fread($stream, 8192);

                    if ($chunk === false) {
                        throw new RuntimeException('Unable to read command output.');
                    }

                    if ($streamName === 'output') {
                        $output .= $chunk;
                    } else {
                        $errorOutput .= $chunk;
                    }

                    if (feof($stream)) {
                        fclose($stream);
                        unset($streams[$streamName]);
                    }
                }
            }

            $exitCode = proc_close($process);
            $processClosed = true;
        } finally {
            foreach ($streams as $stream) {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            if (!$processClosed) {
                proc_terminate($process);
                proc_close($process);
            }
        }

        return new GitCommandResult($output, $errorOutput, $exitCode);
    }
}