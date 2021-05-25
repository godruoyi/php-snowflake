<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Godruoyi\Snowflake;

use Illuminate\Contracts\Cache\Repository;

class LaravelSequenceResolver implements SequenceResolver
{
    /**
     * The laravel cache instance.
     *
     * @var \Illuminate\Contracts\Cache\Repository
     */
    protected $cache;

    /**
     * Init resolve instance, must connectioned.
     */
    public function __construct(Repository $cache)
    {
        $this->cache = $cache;
    }

    /**
     *  {@inheritdoc}
     */
    public function sequence(int $currentTime)
    {
        $key = $currentTime;

        if ($this->cache->add($key, 1, 1)) {
            return 0;
        }

        return $this->cache->increment($key, 1);
    }
}
