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

use Closure;
use Godruoyi\Snowflake\RandomSequenceResolver;
use Godruoyi\Snowflake\SequenceResolver;
use Godruoyi\Snowflake\Snowflake;
use PHPUnit\Framework\Attributes\DataProvider;

class SnowflakeTest extends TestCase
{
    public function test_basic(): void
    {
        $snowflake = new Snowflake();

        $this->assertTrue(! empty($snowflake->id()));
        $this->assertTrue(strlen($snowflake->id()) <= 19);
    }

    public function test_invalid_datacenter_id_and_work_id(): void
    {
        $snowflake = new Snowflake(-1, -1);

        $dataID = $this->invokeProperty($snowflake, 'datacenter');
        $workID = $this->invokeProperty($snowflake, 'workerId');
        $this->assertTrue($workID >= 0 && $workID <= 31);
        $this->assertTrue($dataID >= 0 && $dataID <= 31);

        $snowflake = new Snowflake(33, 33);
        $dataID = $this->invokeProperty($snowflake, 'datacenter');
        $workID = $this->invokeProperty($snowflake, 'workerId');
        $this->assertTrue($workID >= 0 && $workID <= 31);
        $this->assertTrue($dataID >= 0 && $dataID <= 31);

        $snowflake = new Snowflake();
        $dataID = $this->invokeProperty($snowflake, 'datacenter');
        $workID = $this->invokeProperty($snowflake, 'workerId');
        $this->assertTrue($workID >= 0 && $workID <= 31);
        $this->assertTrue($dataID >= 0 && $dataID <= 31);
    }

    public function test_work_id_and_data_center_id(): void
    {
        $snowflake = new Snowflake(-1, -1);

        $this->assertTrue(! empty($snowflake->id()));
        $this->assertTrue(strlen($snowflake->id()) <= 19);

        $snowflake = new Snowflake(33, -1);

        $this->assertTrue(! empty($snowflake->id()));
        $this->assertTrue(strlen($snowflake->id()) <= 19);

        $snowflake = new Snowflake(1, 2);

        $this->assertTrue(! empty($snowflake->id()));
        $this->assertTrue(strlen($id = $snowflake->id()) <= 19);

        $this->assertTrue(1 === $snowflake->parseId($id, true)['datacenter']);
        $this->assertTrue(2 === $snowflake->parseId($id, true)['workerid']);

        $snowflake = new Snowflake(999, 20);
        $id = $snowflake->id();

        $this->assertTrue(999 !== $snowflake->parseId($id, true)['datacenter']);
        $this->assertTrue(20 === $snowflake->parseId($id, true)['workerid']);
    }

    public function test_extends(): void
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

    public function test_batch(): void
    {
        $snowflake = new Snowflake(999, 20);
        $snowflake->setSequenceResolver(function ($currentTime) {
            static $lastTime;
            static $sequence;

            if ($lastTime === $currentTime) {
                $sequence++;
            } else {
                $sequence = 0;
            }

            $lastTime = $currentTime;

            return $sequence;
        });

        $datas = [];

        for ($i = 0; $i < 10000; $i++) {
            $id = $snowflake->id();

            $datas[$id] = 1;
        }

        $this->assertTrue(10000 === count($datas));
    }

    public function test_parse_id(): void
    {
        $snowflake = new Snowflake(999, 20);
        $data = $snowflake->parseId('1537200202186752', false);

        $this->assertSame($data['workerid'], '00000');
        $this->assertSame($data['datacenter'], '00000');
        $this->assertSame($data['sequence'], '000000000000');

        $data = $snowflake->parseId('1537200202186752', true);

        $this->assertTrue(0 === $data['workerid']);
        $this->assertTrue(0 === $data['datacenter']);
        $this->assertTrue(0 === $data['sequence']);
        $this->assertTrue($data['timestamp'] > 0);

        $snowflake = new Snowflake(2, 3);
        $id = $snowflake->id();
        $payloads = $snowflake->parseId($id, true);

        $this->assertTrue(2 === $payloads['datacenter']);
        $this->assertTrue(3 === $payloads['workerid']);
        $this->assertLessThan(Snowflake::MAX_SEQUENCE_SIZE, $payloads['sequence']);

        $payloads = $snowflake->parseId('0');
        $this->assertTrue('' == $payloads['timestamp'] || false == $payloads['timestamp']);
        $this->assertSame($payloads['workerid'], '0');
        $this->assertSame($payloads['datacenter'], '0');
        $this->assertSame($payloads['sequence'], '0');
    }

    public function testget_current_millisecond(): void
    {
        $snowflake = new Snowflake(999, 20);
        $now = floor(microtime(true) * 1000) | 0;
        $time = $snowflake->getCurrentMillisecond();

        $this->assertTrue($time >= $now);
    }

    public function test_set_start_time_stamp(): void
    {
        $snowflake = new Snowflake(999, 20);

        $snowflake->setStartTimeStamp(1);
        $this->assertTrue(1 === $snowflake->getStartTimeStamp());
    }

    public function test_set_start_time_stamp_max_value_is_over(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << 41), You can reset the start time to fix this');

        $snowflake = new Snowflake(-1, -1);
        $snowflake->setStartTimeStamp(strtotime('1900-01-01') * 1000);
    }

    public function test_set_start_time_stamp_cannot_more_that_current_time(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The start time cannot be greater than the current time');

        $snowflake = new Snowflake(999, 20);
        $snowflake->setStartTimeStamp(strtotime('3000-01-01') * 1000);
    }

    public function test_get_start_time_stamp(): void
    {
        $snowflake = new Snowflake(999, 20);
        $defaultTime = '2019-08-08 08:08:08';

        $this->assertTrue($snowflake->getStartTimeStamp() === (strtotime($defaultTime) * 1000));

        $snowflake->setStartTimeStamp(1);
        $this->assertTrue(1 === $snowflake->getStartTimeStamp());
    }

    public function testcall_resolver(): void
    {
        $snowflake = new Snowflake(999, 20);
        $snowflake->setSequenceResolver(function ($currentTime) {
            return 999;
        });

        /** @var Closure $seq */
        $seq = $snowflake->getSequenceResolver();

        $this->assertTrue($seq instanceof Closure);
        $this->assertTrue(999 === $seq(0));
    }

    public function test_get_sequence_resolver(): void
    {
        $snowflake = new Snowflake(999, 20);
        $this->assertTrue(is_null($snowflake->getSequenceResolver()));

        $snowflake->setSequenceResolver(function () {
            return 1;
        });

        $this->assertTrue(is_callable($snowflake->getSequenceResolver()));
    }

    public function test_get_default_sequence_resolver(): void
    {
        $snowflake = new Snowflake(999, 20);
        $this->assertInstanceOf(SequenceResolver::class, $snowflake->getDefaultSequenceResolver());
        $this->assertInstanceOf(RandomSequenceResolver::class, $snowflake->getDefaultSequenceResolver());
    }

    public function test_exception(): void
    {
        $snowflake = new Snowflake();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The start time cannot be greater than the current time');

        $snowflake->setStartTimeStamp(time() * 1000 + 1);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << 41), You can reset the start time to fix this');

        $snowflake->setStartTimeStamp(strtotime('1900-01-01') * 1000);
    }

    public function test_generate_id(): void
    {
        $snowflake = new Snowflake(1, 1);
        $snowflake->setStartTimeStamp(1);
        $snowflake->setSequenceResolver(function ($t) {
            global $startTime;

            if (! $startTime) {
                $startTime = time();
            }

            // sleep 5 seconds
            if (time() - $startTime <= 5) {
                return 4096;
            }

            return 1;
        });

        $this->assertNotEmpty($snowflake->id());
    }

    public static function timestamps_generator(): \Generator
    {
        yield 'DateTime object' => [
            'timestamp' => new \DateTime('2020-01-01 00:00:00'),
            'expectedId' => '6617927201583280132',
            'expectedTimestamp' => 1577836800000,
        ];

        yield 'Microtime int' => [
            'timestamp' => 1577836800000,
            'expectedId' => '6617927201583280132',
            'expectedTimestamp' => 1577836800000,
        ];
    }

    #[DataProvider('timestamps_generator')]
    public function test_generate_id_for_specified_timestamp(\DateTime|int $timestamp, string $expectedId, int $expectedTimestamp): void
    {
        $startTimestamp = 1;
        $datacenter = 2;
        $worker = 3;
        $sequence = 4;

        $snowflake = new Snowflake($datacenter, $worker);
        $snowflake->setStartTimeStamp($startTimestamp);
        $snowflake->setSequenceResolver(fn () => $sequence);

        $id = $snowflake->idFor($timestamp);
        $parsed = $snowflake->parseId($id, true);
        $this->assertEquals([
            'timestamp' => $expectedTimestamp - $startTimestamp,
            'sequence' => $sequence,
            'workerid' => $worker,
            'datacenter' => $datacenter,
        ], $parsed);
        $this->assertEquals($expectedId, $id);
        $this->assertEquals($expectedTimestamp, $snowflake->toMicrotime((int) $parsed['timestamp']));
    }

    public function test_interpretation_of_timestamps(): void
    {
        $startTimestamp = strtotime('2019-08-08 08:08:08') * 1000;
        $expectedTimestamp = 1577836800000;
        $timestamp = 1577836800000 - $startTimestamp;

        $snowflake = new Snowflake(999, 20);
        $snowflake->setStartTimeStamp($startTimestamp);

        $this->assertEquals($expectedTimestamp, $snowflake->toMicrotime($timestamp));
    }
}
