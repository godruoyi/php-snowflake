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

use Godruoyi\Snowflake\Snowflake;

class TimeTest extends TestCase
{
    public function test_time(): void
    {
        $s = new Snowflake();
        $a = 0;

        while (($s1 = $s->getCurrentMillisecond()) && $a < 10) {
            $s2 = $s->getCurrentMillisecond();
            while ($s1 == $s2) {
                usleep(1);
                $s2 = $s->getCurrentMillisecond();
            }
            $a++;

            $this->assertTrue($s1 != $s2);
        }
    }
}
