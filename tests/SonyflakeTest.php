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

use Exception;
use Godruoyi\Snowflake\RandomSequenceResolver;
use Godruoyi\Snowflake\SequenceResolver;
use Godruoyi\Snowflake\Sonyflake;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionException;

class SonyflakeTest extends TestCase
{
    public function test_basic(): void
    {
        $snowflake = new Sonyflake();
        $this->assertInstanceOf(Sonyflake::class, $snowflake);

        $snowflake = new Sonyflake(0);
        $this->assertInstanceOf(Sonyflake::class, $snowflake);
        $this->assertEquals(0, $this->invokeProperty($snowflake, 'machineId'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid machine ID, must be between 0 ~ 65535.');
        $snowflake = new Sonyflake(-1);

        $snowflake = new Sonyflake(65535);
        $this->assertInstanceOf(Sonyflake::class, $snowflake);
        $this->assertEquals(65535, $this->invokeProperty($snowflake, 'machineId'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid machine ID, must be between 0 ~ 65535.');
        $snowflake = new Sonyflake(65536);
    }

    public function test_set_start_time_stamp(): void
    {
        $snowflake = new Sonyflake(110);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Exceeding the maximum life cycle of the algorithm');
        $snowflake->setStartTimeStamp(strtotime('1840-01-01 00:00:00') * 1000); // 2021 - 1840 = 181 > The lifetime (174 years)
    }

    public function test_set_start_time_stamp_cannot_greater_than_current_time(): void
    {
        $snowflake = new Sonyflake(110);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('The start time cannot be greater than the current time');
        $snowflake->setStartTimeStamp(strtotime('2345-01-01 00:00:00') * 1000);

        $snowflake->setStartTimeStamp(1);
        $this->assertEquals(1, $snowflake->getStartTimeStamp());
    }

    public function test_set_start_time_stamp_basic(): void
    {
        $snowflake = new Sonyflake(110);

        $snowflake->setStartTimeStamp(1);

        $this->assertEquals(1, $snowflake->getStartTimeStamp());
    }

    public function test_parse_id(): void
    {
        $snowflake = new Sonyflake(110);
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

    public function test_id(): void
    {
        $snowflake = new Sonyflake();
        $id = $snowflake->id();
        $this->assertTrue(! empty($id));

        $datas = [];
        for ($i = 0; $i < 10000; $i++) {
            $id = $snowflake->id();
            // $this->assertArrayNotHasKey($id, $datas);
            $datas[$id] = 1;
        }
        $this->assertTrue(10000 === count($datas));
    }

    /**
     * @throws ReflectionException
     */
    public function test_generate_id_with_max_elapsed_time(): void
    {
        $snowflake = new Sonyflake(110);
        $reflection = new \ReflectionProperty(get_class($snowflake), 'startTime');
        $reflection->setAccessible(true);
        $reflection->setValue($snowflake, strtotime('1840-01-01 00:00:00') * 1000);

        $this->expectException(Exception::class);
        $snowflake->id();
    }

    public function test_generate_id(): void
    {
        $snowflake = new Sonyflake(1);
        $snowflake->setStartTimeStamp(1);

        $snowflake->setSequenceResolver(function ($t) {
            global $startTime;

            if (! $startTime) {
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

    public static function timestamps_generator(): \Generator
    {
        yield 'DateTime object' => [
            'timestamp' => new \DateTime('2020-01-01 00:00:00'),
            'expectedId' => '2647170880618103556',
            'expectedTimestamp' => 1577836800000,
        ];

        yield 'Microtime int' => [
            'timestamp' => 1577836800000,
            'expectedId' => '2647170880618103556',
            'expectedTimestamp' => 1577836800000,
        ];
    }

    #[DataProvider('timestamps_generator')]
    public function test_generate_id_for_specified_timestamp(\DateTime|int $timestamp, string $expectedId, int $expectedTimestamp): void
    {
        $startTimestamp = 1;
        $worker = 3;
        $sequence = 4;

        $snowflake = new Sonyflake($worker);
        $snowflake->setStartTimeStamp($startTimestamp);
        $snowflake->setSequenceResolver(fn () => $sequence);

        $id = $snowflake->idFor($timestamp);
        $parsed = $snowflake->parseId($id, true);
        $this->assertEquals([
            'timestamp' => (int) floor(($expectedTimestamp - $startTimestamp) / 10),
            'sequence' => $sequence,
            'machineid' => $worker,
        ], $parsed);
        $this->assertEquals($expectedId, $id);
    }

    public function test_get_default_sequence_resolver(): void
    {
        $snowflake = new Sonyflake(1);
        $this->assertInstanceOf(SequenceResolver::class, $snowflake->getDefaultSequenceResolver());
        $this->assertInstanceOf(RandomSequenceResolver::class, $snowflake->getDefaultSequenceResolver());
    }

    public function test_get_sequence_resolver(): void
    {
        $snowflake = new Sonyflake(9);
        $this->assertTrue(is_null($snowflake->getSequenceResolver()));

        $snowflake->setSequenceResolver(function () {
            return 1;
        });

        $this->assertTrue(is_callable($snowflake->getSequenceResolver()));
    }

    public function test_get_start_time_stamp(): void
    {
        $snowflake = new Sonyflake(999);
        $defaultTime = '2019-08-08 08:08:08';

        $this->assertTrue($snowflake->getStartTimeStamp() === (strtotime($defaultTime) * 1000));

        $snowflake->setStartTimeStamp(1);
        $this->assertTrue(1 === $snowflake->getStartTimeStamp());
    }

    public function testget_current_millisecond(): void
    {
        $snowflake = new Sonyflake(9990);
        $now = floor(microtime(true) * 1000) | 0;
        $time = $snowflake->getCurrentMillisecond();

        $this->assertTrue($now - $time >= 0);
    }
}
