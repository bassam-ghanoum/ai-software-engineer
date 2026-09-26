<?php

declare(strict_types=1);

namespace App\AI\Git;

final class GitCommandRunner implements GitCommandRunnerInterface
{
    public function run(string $command): array
    {
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to start Git command.');
        }

        fclose($pipes[0]);

        $output = '';
        $streams = [
            'output' => $pipes[1],
            'error' => $pipes[2],
        ];

        while ($streams !== []) {
            $read = array_values($streams);
            $write = null;
            $except = null;
            $ready = stream_select($read, $write, $except, null);

            if ($ready === false) {
                proc_terminate($process);

                throw new \RuntimeException('Unable to read Git command output.');
            }

            foreach ($read as $stream) {
                $streamName = array_search($stream, $streams, true);
                $chunk = fread($stream, 8192);

                if ($chunk === false) {
                    proc_terminate($process);

                    throw new \RuntimeException('Unable to read Git command output.');
                }

                if ($streamName === 'output') {
                    $output .= $chunk;
                }

                if (feof($stream)) {
                    fclose($stream);
                    unset($streams[$streamName]);
                }
            }
        }

        $exitCode = proc_close($process);

        return [
            'output' => $output,
            'exitCode' => $exitCode,
        ];
    }
}