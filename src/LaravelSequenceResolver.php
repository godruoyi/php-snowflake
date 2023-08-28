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

use Illuminate\Contracts\Cache\Repository;

class LaravelSequenceResolver implements SequenceResolver
{
    /**
     * The cache prefix.
     */
    protected string $prefix = '';

    /**
     * Init resolve instance, must be connected.
     */
    public function __construct(protected Repository $cache) // @phpstan-ignore-line
    {
    }

    public function sequence(int $currentTime): int
    {
        $key = $this->prefix.$currentTime;

        // @phpstan-ignore-next-line
        if ($this->cache->add($key, 1, 10)) {
            return 0;
        }

        // @phpstan-ignore-next-line
        return $this->cache->increment($key, 1);
    }

    public function setCachePrefix(string $prefix): self
    {
        $this->prefix = $prefix;

        return $this;
    }
}
