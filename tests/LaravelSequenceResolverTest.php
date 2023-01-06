<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Godruoyi\Snowflake\LaravelSequenceResolver;
use Illuminate\Contracts\Cache\Repository;

class LaravelSequenceResolverTest extends TestCase
{
    public function testBasic()
    {
        $mock = $this->createStub(Repository::class);

        $mock->method('add')->withAnyParameters()->willReturn(true, false, true);

        $mock->method('increment')->withAnyParameters()->willReturn(1);

        $laravel = new LaravelSequenceResolver($mock);

        $this->assertEquals(0, $laravel->sequence(1));
        $this->assertEquals(1, $laravel->sequence(1));
        $this->assertEquals(0, $laravel->sequence(1));
    }
}
