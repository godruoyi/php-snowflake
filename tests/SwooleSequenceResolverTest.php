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
    }

    public function testBasic(): void
    {
        $snowflake = new SwooleSequenceResolver();

        $this->assertTrue(0 == $snowflake->sequence(0));
        $this->assertTrue(1 == $snowflake->sequence(0));
        $this->assertTrue(2 == $snowflake->sequence(0));
        $this->assertTrue(3 == $snowflake->sequence(0));

        $this->assertTrue(0 == $snowflake->sequence(1));
        $this->assertTrue(1 == $snowflake->sequence(1));
        $this->assertTrue(2 == $snowflake->sequence(1));
    }

    public function testResetLock(): void
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
}
