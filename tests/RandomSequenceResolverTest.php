<?php

namespace Tests;

use Godruoyi\Snowflake\Snowflake;
use Godruoyi\Snowflake\RandomSequenceResolver;

class RandomSequenceResolverTest extends TestCase
{
    public function testBasic()
    {
        $random = new RandomSequenceResolver;

        $this->assertTrue($random->sequence(1) === 0);
        $this->assertTrue($random->sequence(1) === 1);
        $this->assertTrue($random->sequence(2) === 0);
        $this->assertTrue($random->sequence(3) === 0);
        $this->assertTrue($random->sequence(3) === 1);
        $this->assertTrue($random->sequence(4) === 0);
        $this->assertTrue($random->sequence(4) === 1);
    }
}
