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

use Godruoyi\Snowflake\RedisSequenceResolver;
use RedisException;

class RedisSequenceResolverTest extends TestCase
{
    public function test_invalid_redis_connect(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('ping')->willReturn(false);

        $this->expectException(RedisException::class);
        $this->expectExceptionMessage('Redis server went away');
        new RedisSequenceResolver($redis);
    }

    public function test_sequence(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('ping')->willReturn(true);
        $redis->method('eval')->withAnyParameters()->willReturn(0, 1, 2, 3);

        $snowflake = new RedisSequenceResolver($redis);

        $this->assertTrue(0 == $snowflake->sequence(1));
        $this->assertTrue(1 == $snowflake->sequence(1));
        $this->assertTrue(2 == $snowflake->sequence(1));
        $this->assertTrue(3 == $snowflake->sequence(1));
    }

    public function test_set_cache_prefix(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('ping')->willReturn(true);

        $snowflake = new RedisSequenceResolver($redis);
        $snowflake->setCachePrefix('foo');

        $this->assertEquals('foo', $this->invokeProperty($snowflake, 'prefix'));
    }

    /**
     * @throws RedisException
     */
    public function test_real_redis(): void
    {
        if (! extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not installed.');
        }

        if (! ($host = getenv('REDIS_HOST')) || ! ($port = getenv('REDIS_PORT'))) {
            $this->markTestSkipped('Redis host or port is not set, skip real redis test.');
        }

        $redis = new \Redis();
        $redis->connect($host, $port | 0);

        $redisResolver = new RedisSequenceResolver($redis);

        $this->assertEquals(0, $redisResolver->sequence(1));
        $this->assertEquals(1, $redisResolver->sequence(1));
        $this->assertEquals(2, $redisResolver->sequence(1));
        $this->assertEquals(3, $redisResolver->sequence(1));

        sleep(10);

        $this->assertEquals(0, $redisResolver->sequence(1));
    }
}
