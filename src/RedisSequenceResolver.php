<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Godruoyi\Snowflake;

use Redis;
use RedisException;

class RedisSequenceResolver implements SequenceResolver
{
    /**
     * The redis client instance.
     *
     * @var \Redis
     */
    protected $redis;

    /**
     * The cache prefix.
     *
     * @var string
     */
    protected $prefix;

    /**
     * Init resolve instance, must connectioned.
     */
    public function __construct(Redis $redis)
    {
        if ($redis->ping()) {
            $this->redis = $redis;

            return;
        }

        throw new RedisException('Redis server went away');
    }

    /**
     *  {@inheritdoc}
     */
    public function sequence(int $currentTime)
    {
        $lua = <<<'LUA'
if redis.call('set', KEYS[1], ARGV[1], "EX", ARGV[2], "NX") then
    return 0
else
    return redis.call('incr', KEYS[1])
end
LUA;

        // 10 seconds
        return $this->redis->eval($lua, [$this->prefix.$currentTime, '0', '10'], 1);
    }

    /**
     * Set cacge prefix.
     */
    public function setCachePrefix(string $prefix)
    {
        $this->prefix = $prefix;

        return $this;
    }
}
