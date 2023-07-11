<?php

declare(strict_types=1);

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
     * The cache prefix.
     */
    protected string $prefix = '';

    /**
     * Init resolve instance, must be connected.
     */
    public function __construct(protected Redis $redis)
    {
        if (!$redis->ping()) {
            throw new RedisException('Redis server went away');
        }
    }

    public function sequence(int $currentTime): int
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
     * Set cache prefix.
     */
    public function setCachePrefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }
}
