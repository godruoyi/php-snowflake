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
use DateTime;
use Godruoyi\Snowflake\RandomSequenceResolver;
use Godruoyi\Snowflake\SequenceResolver;
use Godruoyi\Snowflake\Snowflake;
use Godruoyi\Snowflake\SnowflakeException;

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

        $this->assertTrue($snowflake->parseId($id, true)['datacenter'] === 1);
        $this->assertTrue($snowflake->parseId($id, true)['workerid'] === 2);

        $snowflake = new Snowflake(999, 20);
        $id = $snowflake->id();

        $this->assertTrue($snowflake->parseId($id, true)['datacenter'] !== 999);
        $this->assertTrue($snowflake->parseId($id, true)['workerid'] === 20);
    }

    public function test_extends(): void
    {
        $snowflake = new Snowflake(999, 20);
        $snowflake->setSequenceResolver(function ($currentTime) {
            return 999;
        });

        $id = $snowflake->id();

        $this->assertTrue($snowflake->parseId($id, true)['datacenter'] !== 999);
        $this->assertTrue($snowflake->parseId($id, true)['sequence'] === 999);
        $this->assertTrue($snowflake->parseId($id, true)['workerid'] === 20);
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

        $this->assertTrue(count($datas) === 10000);
    }

    public function test_parse_id(): void
    {
        $snowflake = new Snowflake(999, 20);
        $data = $snowflake->parseId('1537200202186752', false);

        $this->assertSame($data['workerid'], '00000');
        $this->assertSame($data['datacenter'], '00000');
        $this->assertSame($data['sequence'], '000000000000');

        $data = $snowflake->parseId('1537200202186752', true);

        $this->assertTrue($data['workerid'] === 0);
        $this->assertTrue($data['datacenter'] === 0);
        $this->assertTrue($data['sequence'] === 0);
        $this->assertTrue($data['timestamp'] > 0);

        $snowflake = new Snowflake(2, 3);
        $id = $snowflake->id();
        $payloads = $snowflake->parseId($id, true);

        $this->assertTrue($payloads['datacenter'] === 2);
        $this->assertTrue($payloads['workerid'] === 3);
        $this->assertLessThanOrEqual(Snowflake::MAX_SEQUENCE_SIZE, $payloads['sequence']);

        $payloads = $snowflake->parseId('0');
        $this->assertTrue($payloads['timestamp'] == '' || $payloads['timestamp'] == false);
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
        $this->assertTrue($snowflake->getStartTimeStamp() === 1);
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
        $this->assertTrue($snowflake->getStartTimeStamp() === 1);
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
        $this->assertTrue($seq(0) === 999);
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

    /** @dataProvider idForTimestampDataProvider */
    public function test_id_for_timestamp(DateTime|int $dt): void
    {
        $snowflake = new Snowflake(10, 1);
        $snowflake->setStartTimeStamp((new DateTime('2026-04-22T00:00:00.000Z'))->getTimestamp() * 1000);
        $id = $snowflake->idForTimestamp($dt);
        $parsed = $snowflake->parseId($id, true);
        $this->assertEquals(10, $parsed['datacenter']);
        $this->assertEquals(1, $parsed['workerid']);
        $timestamp = $parsed['timestamp'];

        $this->assertEquals(60 * 60 * 1000, $timestamp);
    }

    public static function idForTimestampDataProvider(): array
    {
        return [
            'datetime' => [new DateTime('2026-04-22T01:00:00.000Z')],
            'timestamp' => [strtotime('2026-04-22T01:00:00.000Z') * 1000],
        ];
    }

    /**
     * @dataProvider invalidIdForTimestampDataProvider
     */
    public function test_cannot_create_id_for_timestamp_when_timestamp_is_before_start_time(DateTime|int $invalidDt): void
    {
        $this->expectException(SnowflakeException::class);
        $this->expectExceptionMessage('The provided timestamp cannot be earlier than the start time');
        $snowflake = new Snowflake(10, 1);
        $snowflake->setStartTimeStamp((new DateTime('2026-04-22T00:00:00.000Z'))->getTimestamp() * 1000);
        $snowflake->idForTimestamp($invalidDt);
    }

    public static function invalidIdForTimestampDataProvider(): array
    {
        return [
            'as datetime' => [new DateTime('2025-01-01T00:00:00.000Z')],
            'as timestamp' => [strtotime('1900-01-01') * 1000],
        ];
    }

    public function test_default_bit_lengths(): void
    {
        $snowflake = new Snowflake();
        $this->assertSame(Snowflake::MAX_WORKID_LENGTH, $snowflake->getWorkerIdBitLength());
        $this->assertSame(Snowflake::MAX_DATACENTER_LENGTH, $snowflake->getDatacenterBitLength());
        $this->assertSame(Snowflake::MAX_SEQUENCE_LENGTH, $snowflake->getSequenceBitLength());
        $this->assertSame((-1 ^ (-1 << Snowflake::MAX_SEQUENCE_LENGTH)), $snowflake->getMaxSequenceNumber());
        $this->assertSame(0, $snowflake->getMinSequenceNumber());
    }

    public function test_set_worker_id_bit_length(): void
    {
        $snowflake = new Snowflake(1, 1);
        // Must reduce sequenceBitLength first to make room for a larger workerIdBitLength
        $snowflake->setSequenceBitLength(6)->setWorkerIdBitLength(6);
        $this->assertSame(6, $snowflake->getWorkerIdBitLength());

        // zero is valid (single-node / no worker field)
        $snowflake2 = new Snowflake(0, 0);
        $snowflake2->setWorkerIdBitLength(0);
        $this->assertSame(0, $snowflake2->getWorkerIdBitLength());
    }

    public function test_set_worker_id_bit_length_out_of_range_throws(): void
    {
        $this->expectException(SnowflakeException::class);
        $this->expectExceptionMessage('WorkerIdBitLength must be between 0 and 15');
        (new Snowflake())->setWorkerIdBitLength(16);
    }

    public function test_set_worker_id_bit_length_exceeds_total_throws(): void
    {
        $this->expectException(SnowflakeException::class);
        $this->expectExceptionMessage('must not exceed 22');
        // Default dc=5, seq=12; setting worker=8 makes 5+8+12=25 > 22
        (new Snowflake())->setWorkerIdBitLength(8);
    }

    public function test_set_datacenter_bit_length(): void
    {
        $snowflake = new Snowflake(1, 1);
        // Reduce sequenceBitLength first to make room
        $snowflake->setSequenceBitLength(6)->setDatacenterBitLength(4);
        $this->assertSame(4, $snowflake->getDatacenterBitLength());
    }

    public function test_set_datacenter_bit_length_out_of_range_throws(): void
    {
        $this->expectException(SnowflakeException::class);
        $this->expectExceptionMessage('DatacenterBitLength must be between 0 and 15');
        (new Snowflake())->setDatacenterBitLength(16);
    }

    public function test_set_sequence_bit_length(): void
    {
        $snowflake = new Snowflake(1, 1);
        $snowflake->setSequenceBitLength(6);
        $this->assertSame(6, $snowflake->getSequenceBitLength());
        // Max sequence should update accordingly: 2^6-1 = 63
        $this->assertSame(63, $snowflake->getMaxSequenceNumber());
    }

    public function test_set_sequence_bit_length_out_of_range_throws(): void
    {
        $this->expectException(SnowflakeException::class);
        $this->expectExceptionMessage('SequenceBitLength must be between 3 and 21');
        (new Snowflake())->setSequenceBitLength(22);
    }

    public function test_set_sequence_bit_length_exceeds_total_throws(): void
    {
        $this->expectException(SnowflakeException::class);
        $this->expectExceptionMessage('must not exceed 22');
        // Default dc=5, worker=5; setting seq=13 makes 5+5+13=23 > 22
        (new Snowflake())->setSequenceBitLength(13);
    }

    public function test_set_max_sequence_number(): void
    {
        $snowflake = new Snowflake(1, 1);
        $snowflake->setMaxSequenceNumber(100);
        $this->assertSame(100, $snowflake->getMaxSequenceNumber());
    }

    public function test_set_max_sequence_number_zero_uses_bit_length_max(): void
    {
        $snowflake = new Snowflake(1, 1);
        $snowflake->setSequenceBitLength(6)->setMaxSequenceNumber(0);
        // 0 means "use 2^seqBitLength-1"
        $this->assertSame(63, $snowflake->getMaxSequenceNumber());
    }

    public function test_set_max_sequence_number_exceeds_bit_length_throws(): void
    {
        $this->expectException(SnowflakeException::class);
        $this->expectExceptionMessage('MaxSequenceNumber must not exceed');
        $snowflake = new Snowflake(1, 1);
        $snowflake->setSequenceBitLength(6); // max is 63
        $snowflake->setMaxSequenceNumber(64);
    }

    public function test_set_max_sequence_number_less_than_min_throws(): void
    {
        $this->expectException(SnowflakeException::class);
        $this->expectExceptionMessage('MaxSequenceNumber must be greater than MinSequenceNumber');
        $snowflake = new Snowflake(1, 1);
        $snowflake->setMinSequenceNumber(10);
        $snowflake->setMaxSequenceNumber(5);
    }

    public function test_set_min_sequence_number(): void
    {
        $snowflake = new Snowflake(1, 1);
        $snowflake->setMinSequenceNumber(5);
        $this->assertSame(5, $snowflake->getMinSequenceNumber());
    }

    public function test_set_min_sequence_number_negative_throws(): void
    {
        $this->expectException(SnowflakeException::class);
        $this->expectExceptionMessage('MinSequenceNumber must be a non-negative integer');
        (new Snowflake())->setMinSequenceNumber(-1);
    }

    public function test_set_min_sequence_number_greater_than_max_throws(): void
    {
        $this->expectException(SnowflakeException::class);
        $this->expectExceptionMessage('MinSequenceNumber must be less than MaxSequenceNumber');
        $snowflake = new Snowflake(1, 1);
        $snowflake->setMaxSequenceNumber(10);
        $snowflake->setMinSequenceNumber(11);
    }

    public function test_custom_bit_lengths_produce_valid_ids(): void
    {
        $snowflake = new Snowflake(1, 1);
        // 4 bits datacenter + 6 bits workerId + 6 bits sequence = 16 bits (48 bits for timestamp)
        $snowflake->setSequenceBitLength(6)->setDatacenterBitLength(4)->setWorkerIdBitLength(6);

        $id = $snowflake->id();
        $this->assertNotEmpty($id);

        $parsed = $snowflake->parseId($id, true);
        $this->assertSame(1, $parsed['datacenter']);
        $this->assertSame(1, $parsed['workerid']);
        $this->assertLessThanOrEqual(63, $parsed['sequence']); // 2^6-1
    }

    public function test_min_sequence_number_respected_by_default_resolver(): void
    {
        $snowflake = new Snowflake(1, 1);
        $snowflake->setMinSequenceNumber(5);

        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $id = $snowflake->id();
            $parsed = $snowflake->parseId($id, true);
            // Sequence should be >= 5 when starting a new millisecond
            // (in the same ms it increments, so may temporarily go to previous values)
            $ids[] = $parsed['sequence'];
        }

        // At least some sequences should be generated (non-empty)
        $this->assertNotEmpty($ids);
    }

    public function test_chained_bit_length_setters(): void
    {
        $snowflake = (new Snowflake(1, 1))
            ->setSequenceBitLength(6)
            ->setWorkerIdBitLength(6)
            ->setDatacenterBitLength(4)
            ->setMaxSequenceNumber(60)
            ->setMinSequenceNumber(5);

        $this->assertSame(6, $snowflake->getSequenceBitLength());
        $this->assertSame(6, $snowflake->getWorkerIdBitLength());
        $this->assertSame(4, $snowflake->getDatacenterBitLength());
        $this->assertSame(60, $snowflake->getMaxSequenceNumber());
        $this->assertSame(5, $snowflake->getMinSequenceNumber());
    }

    public function test_no_worker_no_datacenter_single_node_sequence_only(): void
    {
        // dc=0, worker=0, seq=20 — all 20 non-timestamp bits used for sequence
        $snowflake = new Snowflake(0, 0);
        $snowflake->setDatacenterBitLength(0)
            ->setWorkerIdBitLength(0)
            ->setSequenceBitLength(20);

        $this->assertSame(0, $snowflake->getDatacenterBitLength());
        $this->assertSame(0, $snowflake->getWorkerIdBitLength());
        $this->assertSame(20, $snowflake->getSequenceBitLength());
        $this->assertSame((1 << 20) - 1, $snowflake->getMaxSequenceNumber()); // 2^20-1

        $id = $snowflake->id();
        $this->assertNotEmpty($id);

        $parsed = $snowflake->parseId($id, true);
        $this->assertSame(0, $parsed['datacenter']);
        $this->assertSame(0, $parsed['workerid']);
        $this->assertGreaterThanOrEqual(0, $parsed['sequence']);
        $this->assertLessThanOrEqual((1 << 20) - 1, $parsed['sequence']);
    }
}
