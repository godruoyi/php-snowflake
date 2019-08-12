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

class SnowflakeTest extends TestCase
{
    public function testBasic()
    {
        $snowflake = new Snowflake();

        $this->assertTrue(!empty($snowflake->id()));
        $this->assertTrue(16 == strlen($snowflake->id()));
    }

    public function testWorkIDAndDataCenterId()
    {
        $snowflake = new Snowflake(-1, -1);

        $this->assertTrue(!empty($snowflake->id()));
        $this->assertTrue(16 == strlen($snowflake->id()));

        $snowflake = new Snowflake(33, -1);

        $this->assertTrue(!empty($snowflake->id()));
        $this->assertTrue(16 == strlen($snowflake->id()));

        $snowflake = new Snowflake(1, 2);

        $this->assertTrue(!empty($snowflake->id()));
        $this->assertTrue(16 == strlen($id = $snowflake->id()));

        $this->assertTrue(1 == $snowflake->parseId($id, true)['datacenter']);
        $this->assertTrue(2 == $snowflake->parseId($id, true)['workerid']);

        $snowflake = new Snowflake(999, 20);
        $id = $snowflake->id();

        $this->assertTrue(999 != $snowflake->parseId($id, true)['datacenter']);
        $this->assertTrue(20 == $snowflake->parseId($id, true)['workerid']);
    }

    public function testExtends()
    {
        $snowflake = new Snowflake(999, 20);
        $snowflake->setSequenceResolver(function ($currentTime) {
            return 999;
        });

        $id = $snowflake->id();

        $this->assertTrue(999 != $snowflake->parseId($id, true)['datacenter']);
        $this->assertTrue(999 == $snowflake->parseId($id, true)['sequence']);
        $this->assertTrue(20 == $snowflake->parseId($id, true)['workerid']);
    }

    public function testBatch()
    {
        $snowflake = new Snowflake(999, 20);
        $snowflake->setSequenceResolver(function ($currentTime) {
            static $lastTime;
            static $sequence;

            if ($lastTime == $currentTime) {
                ++$sequence;
            }

            $lastTime = $currentTime;

            return $sequence;
        });

        $datas = [];

        for ($i = 0; $i < 1000; ++$i) {
            $id = $snowflake->id();

            $datas[$id] = 1;
        }

        $this->assertTrue(1000 == count($datas));
    }
}
