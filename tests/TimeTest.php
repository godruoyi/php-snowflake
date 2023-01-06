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

class TimeTest extends TestCase
{
    public function testTime()
    {
        $s = new Snowflake();
        $a = 0;

        while (($s1 = $s->getcurrentMicrotime()) && $a < 10) {
            $s2 = $s->getcurrentMicrotime();
            while ($s1 == $s2) {
                usleep(1);
                $s2 = $s->getcurrentMicrotime();
            }
            $a++;
            $this->assertTrue($s1 != $s2);
        }
    }
}
