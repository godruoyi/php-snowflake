<?php

declare(strict_types=1);

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Closure;
use Godruoyi\Snowflake\FileLockResolver;
use Godruoyi\Snowflake\RedisSequenceResolver;
use Godruoyi\Snowflake\Snowflake;
use RuntimeException;
use Throwable;

class BatchSnowflakeIDTest extends TestCase
{
    /**
     * @var int The start timestamp of snowflake, its value is the timestamp of 2023-01-01 00:00:00
     *
     * @see Snowflake::startTimeStamp
     */
    protected int $startTimeStamp = 1672502400000;

    protected function setUp(): void
    {
        (new FileLockResolver())->cleanAllLocksFile();
    }

    protected function tearDown(): void
    {
        (new FileLockResolver())->cleanAllLocksFile();
    }

    public function test_batch_use_same_instance(): void
    {
        $ids = [];
        $count = 100000;
        $snowflake = new Snowflake();

        for ($i = 0; $i < $count; $i++) {
            $id = $snowflake->id();
            $ids[$id] = 1;
        }

        $this->assertCount($count, $ids);
    }

    public function test_batch_for_diff_instance(): void
    {
        $ids = [];
        $count = 100000; // 10w

        for ($i = 0; $i < $count; $i++) {
            $ids[(new Snowflake())->id()] = 1;
        }

        $this->assertNotCount($count, $ids);
        $this->assertGreaterThan(90000, count($ids));
    }

    public function test_batch_for_same_instance(): void
    {
        $ids = [];
        $count = 100000; // 10w
        $snowflake = new Snowflake();

        for ($i = 0; $i < $count; $i++) {
            $ids[$snowflake->id()] = 1;
        }

        $this->assertCount($count, $ids);
    }

    public function test_run_parallel_with_static_variable()
    {
        $parallel = 10;
        $numbers = 1000;

        static $id;
        $increment = function () use ($id, $numbers) {
            $ids = [];
            for ($i = 0; $i < $numbers; $i++) {
                $id++;
                $ids[] = $id;
            }

            return $ids;
        };

        /** @var array $result */
        $result = $this->runParallel($increment, $parallel);

        $this->assertCount($parallel, $result);
        for ($i = 0; $i < $parallel; $i++) {
            $this->assertCount($numbers, $result[$i]['data']);
            $this->assertNull($result[$i]['error']);
        }

        $ids = [];
        foreach ($result as $item) {
            foreach ($item['data'] as $id) {
                $ids[$id] = 1;
            }
        }

        // static variable will be shared in all processes, so the count of ids will be less than $parallel * $numbers
        $this->assertLessThan($parallel * $numbers, count($ids));
    }

    /**
     * @dataProvider provideSequenceResolvers
     *
     * @return void
     */
    public function test_can_generate_unique_id($parallel, $iterations, $resolver)
    {
        $result = $this->runParallel($this->generateUniqueID($iterations, $resolver), $parallel);

        $this->assertCount($parallel, $result);

        $ids = [];
        foreach ($result as $item) {
            if (! is_null($item['error'])) {
                $this->fail($item['error']);
            }
            $this->assertCount($iterations, $item['data']);

            foreach ($item['data'] as $id => $v) {
                $ids[$id] = $v;
            }
        }

        $this->assertCount($iterations * $parallel, $ids);
    }

    /**
     * @return array[] [parallel, iterations, resolver]
     */
    public static function provideSequenceResolvers(): array
    {
        $resolvers = [
            [100, 1000, function () { // will generate 100w unique ids
                FileLockResolver::$shardCount = 64;

                return new FileLockResolver();
            }],
        ];

        if (extension_loaded('redis') && getenv('REDIS_HOST') && getenv('REDIS_PORT')) {
            $resolvers[] = [100, 3000, function () { // will generate 300w unique ids
                $redis = new \Redis();
                $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT') | 0);

                return new RedisSequenceResolver($redis);
            }];
        }

        return $resolvers;
    }

    /**
     * Run specified callback in parallel.
     *
     * @param  callable  $callback
     * @param  int  $parallel
     * @return array<int, array>
     */
    protected function runParallel(callable $callback, int $parallel = 100): array
    {
        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('The pcntl extension is not available.');
        }

        $children = [];
        $results = [];
        $pipes = $this->createPipelines($parallel);

        for ($i = 0; $i < $parallel; $i++) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                throw new RuntimeException('Error creating process.');
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

        foreach ($children as $child) {
            $results[] = json_decode(stream_get_contents($child['pipe']), true);

            pcntl_waitpid($child['pid'], $status);
        }

        return $results;
    }

    /**
     * Create pipelines with specified number, will fire a exception if failed.
     *
     * @param  int  $parallel
     * @return array
     */
    private function createPipelines(int $parallel = 100): array
    {
        $pipes = [];
        for ($i = 0; $i < $parallel; $i++) {
            $pipe = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if ($pipe === false) {
                throw new RuntimeException('Error creating pipe.');
            }

            $pipes[] = $pipe;
        }

        return $pipes;
    }

    /**
     * Generate unique ids with specified sequence resolver.
     *
     * @param  int  $iterations
     * @param  callable  $sequenceResolver
     * @return Closure
     */
    protected function generateUniqueID(int $iterations, callable $sequenceResolver): Closure
    {
        return function () use ($iterations, $sequenceResolver) {
            $snowflake = (new Snowflake(0, 0))
                ->setSequenceResolver($sequenceResolver())
                ->setStartTimeStamp($this->startTimeStamp);

            $ids = [];
            for ($i = 0; $i < $iterations; $i++) {
                $id = $snowflake->id();
                $ids[$id] = true;
            }

            return $ids;
        };
    }
}
