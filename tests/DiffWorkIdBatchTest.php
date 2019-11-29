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

class DiffWorkIdBatchTest extends TestCase
{
    public function testDissWorkID()
    {
        $snowflake = new Snowflake(1, 1);

        $ids = [];

        for ($i = 0; $i < 10000; ++$i) {
            $id = $snowflake->id();

            $ids[$id] = 1;
        }

        $snowflake = new Snowflake(1, 2);

        for ($j = 0; $j < 10000; ++$j) {
            $id = $snowflake->id();

            $ids[$id] = 1;
        }

        $this->assertTrue(20000 === count($ids));
    }
}
