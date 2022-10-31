<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Godruoyi\Snowflake\Snowflake;

class BatchSnowflakeIDTest extends TestCase
{
    public function testBatchUseSameInstance()
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

    public function testBatchForDiffInstance()
    {
        $ids = [];
        $count = 100000; // 10w

        for ($i = 0; $i < $count; $i++) {
            $ids[(new Snowflake())->id()] = 1;
        }

        $this->assertNotCount($count, $ids);
        $this->assertGreaterThan(90000, count($ids));
    }
}
