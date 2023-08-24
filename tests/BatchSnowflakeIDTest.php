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

use Godruoyi\Snowflake\FileLockResolver;
use Godruoyi\Snowflake\RedisSequenceResolver;
use Godruoyi\Snowflake\Snowflake;
use Throwable;

class BatchSnowflakeIDTest extends TestCase
{
    public function test_batch_for_same_instance_with_default_driver(): void
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

    public function test_batch_for_diff_instance_with_default_driver(): void
    {
        $ids = [];
        $count = 100000; // 10w

        for ($i = 0; $i < $count; $i++) {
            $ids[(new Snowflake())->id()] = 1;
        }

        $this->assertNotCount($count, $ids);
        $this->assertGreaterThan(90000, count($ids));
    }

    public function test_batch_for_diff_instance_with_redis_driver()
    {
        if (! extension_loaded('redis')
            || ! getenv('REDIS_HOST')
            || ! getenv('REDIS_PORT')) {
            $this->markTestSkipped('Redis extension is not installed or not configured.');
        }

        if (! extension_loaded('pcntl')) {
            $this->markTestSkipped('The pcntl extension is not installed.');
        }

        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST'), getenv('REDIS_PORT') | 0);

        $snowflake = (new Snowflake(0, 0))
            ->setSequenceResolver(new RedisSequenceResolver($redis))
            ->setStartTimeStamp(strtotime('2022-12-14') * 1000);

        $this->parallelRun($snowflake, 100, 10000);
    }

    public function test_batch_for_diff_instance_with_file_driver()
    {
        $fileResolver = new FileLockResolver(__DIR__.'/../.locks');
        $fileResolver->cleanAllLocksFile();

        $snowflake = (new Snowflake(0, 0))
            ->setSequenceResolver($fileResolver)
            ->setStartTimeStamp(strtotime('2022-12-14') * 1000);

        $this->parallelRun($snowflake, 100, 1000);

        $fileResolver->cleanAllLocksFile();
    }

    /**
     * Runs the given function in parallel using the specified number of processes.
     *
     * @param  Snowflake  $snowflake A Snowflake instance used to generate unique IDs.
     * @param  int  $parallel The number of processes to run in parallel.
     * @param  int  $count The number of times to run the function.
     * @return void
     *
     * @throws Throwable
     */
    protected function parallelRun(Snowflake $snowflake, int $parallel, int $count): void
    {
        $results = Support\Parallel::run(function () use ($snowflake, $count) {
            $ids = [];
            for ($i = 0; $i < $count; $i++) {
                $ids[] = $snowflake->id();
            }

            return $ids;
        }, $parallel);

        $this->assertResults($results, $parallel, $count);
    }

    /**
     * Asserts the results of a parallel execution.
     *
     * @param  array  $results The array of results.
     * @param  int  $parallel The number of parallel executions.
     * @param  int  $count The expected count for each execution.
     * @return void
     */
    private function assertResults(array $results, int $parallel, int $count): void
    {
        $this->assertCount($parallel, $results);

        $ids = [];
        foreach ($results as $result) {
            $this->assertArrayHasKey('data', $result);
            $this->assertArrayHasKey('error', $result);
            if ($result['error']) {
                $this->fail($result['error']);
            }
            $this->assertCount($count, $result['data']);
            $this->assertNull($result['error']);

            foreach ($result['data'] as $id) {
                $ids[$id] = 1;
            }
        }

        $this->assertCount($parallel * $count, $ids);
    }
}
