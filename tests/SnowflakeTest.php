<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Godruoyi\Snowflake\RandomSequenceResolver;
use Godruoyi\Snowflake\SequenceResolver;
use Godruoyi\Snowflake\Snowflake;

class SnowflakeTest extends TestCase
{
    public function testBasic()
    {
        $snowflake = new Snowflake();

        $this->assertTrue(!empty($snowflake->id()));
        $this->assertTrue(strlen($snowflake->id()) <= 19);
    }

    public function testWorkIDAndDataCenterId()
    {
        $snowflake = new Snowflake(-1, -1);

        $this->assertTrue(!empty($snowflake->id()));
        $this->assertTrue(strlen($snowflake->id()) <= 19);

        $snowflake = new Snowflake(33, -1);

        $this->assertTrue(!empty($snowflake->id()));
        $this->assertTrue(strlen($snowflake->id()) <= 19);

        $snowflake = new Snowflake(1, 2);

        $this->assertTrue(!empty($snowflake->id()));
        $this->assertTrue(strlen($id = $snowflake->id()) <= 19);

        $this->assertTrue(1 === $snowflake->parseId($id, true)['datacenter']);
        $this->assertTrue(2 === $snowflake->parseId($id, true)['workerid']);

        $snowflake = new Snowflake(999, 20);
        $id = $snowflake->id();

        $this->assertTrue(999 !== $snowflake->parseId($id, true)['datacenter']);
        $this->assertTrue(20 === $snowflake->parseId($id, true)['workerid']);
    }

    public function testExtends()
    {
        $snowflake = new Snowflake(999, 20);
        $snowflake->setSequenceResolver(function ($currentTime) {
            return 999;
        });

        $id = $snowflake->id();

        $this->assertTrue(999 !== $snowflake->parseId($id, true)['datacenter']);
        $this->assertTrue(999 === $snowflake->parseId($id, true)['sequence']);
        $this->assertTrue(20 === $snowflake->parseId($id, true)['workerid']);
    }

    public function testBatch()
    {
        $snowflake = new Snowflake(999, 20);
        $snowflake->setSequenceResolver(function ($currentTime) {
            static $lastTime;
            static $sequence;

            if ($lastTime === $currentTime) {
                ++$sequence;
            } else {
                $sequence = 0;
            }

            $lastTime = $currentTime;

            return $sequence;
        });

        $datas = [];

        for ($i = 0; $i < 10000; ++$i) {
            $id = $snowflake->id();

            $datas[$id] = 1;
        }

        $this->assertTrue(10000 === count($datas));
    }

    public function testParseId()
    {
        $snowflake = new Snowflake(999, 20);
        $data = $snowflake->parseId('1537200202186752');

        $this->assertSame($data['workerid'], '00000');
        $this->assertSame($data['datacenter'], '00000');
        $this->assertSame($data['sequence'], '000000000000');

        $data = $snowflake->parseId('1537200202186752', true);

        $this->assertTrue(0 === $data['workerid']);
        $this->assertTrue(0 === $data['datacenter']);
        $this->assertTrue(0 === $data['sequence']);
    }

    public function testGetCurrentMicrotime()
    {
        $snowflake = new Snowflake(999, 20);
        $now = floor(microtime(true) * 1000) | 0;
        $time = $snowflake->getCurrentMicrotime();

        $this->assertTrue($now - $time >= 0);
    }

    public function testSetStartTimeStamp()
    {
        $snowflake = new Snowflake(999, 20);

        $snowflake->setStartTimeStamp(1);
        $this->assertTrue(1 === $snowflake->getStartTimeStamp());

        $this->assertTrue(strlen($snowflake->id()) <= 19);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << 41), You can reset the start time to fix this');

        $snowflake = new Snowflake(-1, -1);
        $snowflake->setStartTimeStamp(strtotime('1900-01-01') * 1000);
    }

    public function testGetStartTimeStamp()
    {
        $snowflake = new Snowflake(999, 20);
        $defaultTime = '2019-08-08 08:08:08';

        $this->assertTrue($snowflake->getStartTimeStamp() === (strtotime($defaultTime) * 1000));

        $snowflake->setStartTimeStamp(1);
        $this->assertTrue(1 === $snowflake->getStartTimeStamp());
    }

    public function testGetSequenceResolver()
    {
        $snowflake = new Snowflake(999, 20);
        $this->assertTrue(is_null($snowflake->getSequenceResolver()));

        $snowflake->setSequenceResolver(function () {
            return 1;
        });

        $this->assertTrue(is_callable($snowflake->getSequenceResolver()));
    }

    public function testGetDefaultSequenceResolver()
    {
        $snowflake = new Snowflake(999, 20);
        $this->assertInstanceOf(SequenceResolver::class, $snowflake->getDefaultSequenceResolver());
        $this->assertInstanceOf(RandomSequenceResolver::class, $snowflake->getDefaultSequenceResolver());
    }

    public function testException()
    {
        $snowflake = new Snowflake;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The start time cannot be greater than the current time');

        $snowflake->setStartTimeStamp(time()*1000 + 1);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << 41), You can reset the start time to fix this');

        $snowflake->setStartTimeStamp(strtotime('1900-01-01') * 1000);
    }
}
