<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Godruoyi\Snowflake\IceFlake;
use Godruoyi\Snowflake\RandomSequenceResolver;
use Godruoyi\Snowflake\SequenceResolver;

class IceFlakeTest extends TestCase
{
    public function testBasic()
    {
        $snowflake = new IceFlake();
        $this->assertInstanceOf(IceFlake::class, $snowflake);

        $snowflake = new IceFlake(0);
        $this->assertInstanceOf(IceFlake::class, $snowflake);
        $this->assertEquals(0, $this->invokeProperty($snowflake, "machineid"));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid machine ID, must be between 0 ~ 511.');
        $snowflake = new IceFlake(-1);

        $snowflake = new IceFlake(511);
        $this->assertInstanceOf(IceFlake::class, $snowflake);
        $this->assertEquals(511, $this->invokeProperty($snowflake, "machineid"));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid machine ID, must be between 0 ~ 511.');
        $snowflake = new IceFlake(512);
    }

    public function testSetStartTimeStamp()
    {
        $snowflake = new IceFlake(110);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Exceeding the maximum life cycle of the algorithm');
        $snowflake->setStartTimeStamp(strtotime('1840-01-01 00:00:00')); // 2021 - 1840 = 181 > The lifetime (174 years)
    }

    public function testSetStartTimeStampCannotGreaterThanCurrentTime () {
        $snowflake = new IceFlake(110);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The start time cannot be greater than the current time');
        $snowflake->setStartTimeStamp(strtotime('2345-01-01 00:00:00'));

        $snowflake->setStartTimeStamp(1);
        $this->assertEquals(1, $snowflake->getStartTimeStamp());
    }

    public function testSetStartTimeStampBasic () {
        $snowflake = new IceFlake(110);

        $snowflake->setStartTimeStamp(1);
        $this->assertEquals(1, $snowflake->getStartTimeStamp());
    }

    public function testParseId()
    {
        $snowflake = new IceFlake(110);
        $id = $snowflake->id();

        $dumps = $snowflake->parseId($id);
        $this->assertArrayHasKey('sequence', $dumps);
        $this->assertArrayHasKey('machineid', $dumps);
        $this->assertArrayHasKey('timestamp', $dumps);
        $this->assertTrue(decbin(110) == $dumps['machineid']);

        $dumps = $snowflake->parseId($id, true);
        $this->assertArrayHasKey('sequence', $dumps);
        $this->assertArrayHasKey('machineid', $dumps);
        $this->assertArrayHasKey('timestamp', $dumps);
        $this->assertTrue(110 == $dumps['machineid']);
    }

    public function testId()
    {
        $snowflake = new IceFlake();
        $id = $snowflake->id();
        $this->assertTrue(!empty($id));

        $datas = [];
        for ($i = 0; $i < 100; ++$i) {
            $id = $snowflake->id();
            // $this->assertArrayNotHasKey($id, $datas);
            $datas[$id] = 1;
        }
        $this->assertTrue(100 === count($datas));
    }

    public function testGenerateID() {
        $snowflake = new IceFlake(1);
        $snowflake->setStartTimeStamp(1);

        $snowflake->setSequenceResolver(function ($t) {
            global $startTime;

            if (!$startTime) {
                $startTime = time();
            }

            // sleep 10 seconds
            if (time() - $startTime <= 5) {
                return 256;
            }
            return 1;
        });

        $this->assertNotEmpty($snowflake->id());
    }

    public function testGetDefaultSequenceResolver()
    {
        $snowflake = new IceFlake(1);
        $this->assertInstanceOf(SequenceResolver::class, $snowflake->getDefaultSequenceResolver());
        $this->assertInstanceOf(RandomSequenceResolver::class, $snowflake->getDefaultSequenceResolver());
    }

    public function testGetSequenceResolver()
    {
        $snowflake = new IceFlake(9);
        $this->assertTrue(is_null($snowflake->getSequenceResolver()));

        $snowflake->setSequenceResolver(function () {
            return 1;
        });

        $this->assertTrue(is_callable($snowflake->getSequenceResolver()));
    }

    public function testGetStartTimeStamp()
    {
        $snowflake = new IceFlake(111);
        $defaultTime = '2022-02-06 17:53:00';

        $this->assertTrue($snowflake->getStartTimeStamp() === (strtotime($defaultTime)));

        $snowflake->setStartTimeStamp(1);
        $this->assertTrue(1 === $snowflake->getStartTimeStamp());
    }

}
