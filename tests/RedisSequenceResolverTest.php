<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Godruoyi\Snowflake\RedisSequenceResolver;

/**
 * @internal
 * @coversNothing
 */
class RedisSequenceResolverTest extends TestCase
{
    public function testSequence()
    {
        $redis = new \Redis();
        $redis->connect(getenv('REDIS_HOST') ?: '127.0.0.1');

        $cachePrefix = 'test:';
        $currentTime = floor(microtime(true) * 1000);
        $cacheKey = $cachePrefix.$currentTime;

        $snowflake = new RedisSequenceResolver($redis);
        $snowflake->setCachePrefix($cachePrefix);

        $this->assertTrue(0 == $snowflake->sequence($currentTime));
        $this->assertTrue(1 == $snowflake->sequence($currentTime));
        $this->assertTrue(2 == $snowflake->sequence($currentTime));
        $this->assertTrue(3 == $snowflake->sequence($currentTime));
        $this->assertTrue(3 == $redis->get($cacheKey));
    }
}
