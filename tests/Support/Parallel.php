<?php

declare(strict_types=1);

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests\Support;

use RuntimeException;
use Throwable;

final class Parallel
{
    /**
     * Run specified callback in parallel.
     *
     * @param  callable  $callback
     * @param  int  $parallel
     * @return array
     *
     * @throws RuntimeException|Throwable
     */
    public static function run(callable $callback, int $parallel = 100): array
    {
        if (! extension_loaded('pcntl')) {
            return [];
        }

        $children = self::createChildProcess($callback, $parallel);

        $results = [];
        foreach ($children as $child) {
            $results[] = json_decode(stream_get_contents($child['pipe']), true);
            pcntl_waitpid($child['pid'], $status);
        }

        return $results;
    }

    /**
     * Creates child processes to execute a callback function in parallel.
     *
     * @param  callable  $callback  The callback function to execute in each child process.
     * @param  int  $parallel  The number of child processes to create (default: 100).
     * @return array An array of child process information, including the process ID and the pipe.
     *
     * @throws RuntimeException If a child process cannot be created.
     */
    private static function createChildProcess(callable $callback, int $parallel = 100): array
    {
        if (! extension_loaded('pcntl')) {
            return [];
        }

        $children = [];
        $pipes = self::createPipelines($parallel);

        for ($i = 0; $i < $parallel; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('can not create child process');
            } elseif ($pid === 0) {
                fclose($pipes[$i][0]);
                try {
                    $result = ['data' => $callback(), 'error' => null];
                } catch (Throwable $e) {
                    $result = ['error' => $e->getMessage(), 'data' => []];
                }
                fwrite($pipes[$i][1], json_encode($result));
                fclose($pipes[$i][1]);
                exit(0);
            } else {
                fclose($pipes[$i][1]);
                $children[] = ['pid' => $pid, 'pipe' => $pipes[$i][0]];
            }
        }

        return $children;
    }

    /**
     * Create pipelines with specified number, will fire a exception if failed.
     *
     * @param  int  $parallel
     * @return array
     */
    private static function createPipelines(int $parallel = 100): array
    {
        $pipes = [];
        for ($i = 0; $i < $parallel; $i++) {
            $pipe = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($pipe === false) {
                throw new RuntimeException('create pipelines failed');
            }

            $pipes[] = $pipe;
        }

        return $pipes;
    }
}
