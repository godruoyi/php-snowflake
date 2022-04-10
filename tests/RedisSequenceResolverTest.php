<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use RedisException;
use Godruoyi\Snowflake\RedisSequenceResolver;

class RedisSequenceResolverTest extends TestCase
{
    public function testInvalidRedisConnect() {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('ping')->willThrowException(new \RedisException('foo'));

        $this->expectException(RedisException::class);
        $this->expectExceptionMessage('foo');
        new RedisSequenceResolver($redis);
    }

    public function testSequence()
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

    public function testSetCachePrefix()
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())->method('ping')->willReturn(true);

        $snowflake = new RedisSequenceResolver($redis);
        $snowflake->setCachePrefix('foo');

       $this->assertEquals('foo', $this->invokeProperty($snowflake, 'prefix'));
    }
}
