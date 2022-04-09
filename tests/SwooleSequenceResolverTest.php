<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Godruoyi\Snowflake\SwooleSequenceResolver;

/**
 * @internal
 * @coversNothing
 */
class SwooleSequenceResolverTest extends TestCase
{
    public function testBasic()
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

    public function testResetLock() {
        $snowflake = new SwooleSequenceResolver();

        $lock = $this->createMock('Swoole\Lock');
        $lock->expects($this->any())->method('trylock')->willReturn(false);

        $snowflake->resetLock($lock);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Swoole lock failure, Unable to get the program lock after many attempts.');
        $snowflake->sequence(1);
    }
}
