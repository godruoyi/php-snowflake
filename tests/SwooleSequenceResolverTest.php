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

use Godruoyi\Snowflake\SwooleSequenceResolver;

class SwooleSequenceResolverTest extends TestCase
{
    public function setUp(): void
    {
        if (version_compare(PHP_VERSION, '8.3') >= 0) {
            $this->markTestSkipped('Swoole does not yet support PHP 8.3');
        }

        if (! extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not installed');
        }
    }

    public function test_basic(): void
    {
        $snowflake = new SwooleSequenceResolver();

        $this->assertTrue($snowflake->sequence(0) == 0);
        $this->assertTrue($snowflake->sequence(0) == 1);
        $this->assertTrue($snowflake->sequence(0) == 2);
        $this->assertTrue($snowflake->sequence(0) == 3);

        $this->assertTrue($snowflake->sequence(1) == 0);
        $this->assertTrue($snowflake->sequence(1) == 1);
        $this->assertTrue($snowflake->sequence(1) == 2);
    }

    public function test_reset_lock(): void
    {
        $snowflake = new SwooleSequenceResolver();

        $lock = $this->createStub(\Swoole\Lock::class);
        $lock->expects($this->any())->method('trylock')->willReturn(false);
        $lock->expects($this->any())->method('unlock')->willReturn(true);

        $snowflake->resetLock($lock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Swoole lock failure, Unable to get the program lock after many attempts.');

        while (true) {
            $snowflake->sequence(1);
        }
    }

    public function test_real_swoole()
    {
        if (! extension_loaded('swoole')) {
            $this->markTestSkipped('Swoole extension is not installed.');
        }

        $snowflake = new SwooleSequenceResolver();
        $this->assertEquals(0, $snowflake->sequence(0));
        $this->assertEquals(1, $snowflake->sequence(0));
        $this->assertEquals(2, $snowflake->sequence(0));
        $this->assertEquals(3, $snowflake->sequence(0));
    }
}
