<?php

namespace Tests;

use Godruoyi\Snowflake\RandomSequenceResolver;

class RandomSequenceResolverTest extends TestCase
{
    public function testBasic()
    {
        $random = new RandomSequenceResolver();

        $this->assertTrue(0 === $random->sequence(1));
        $this->assertTrue(1 === $random->sequence(1));
        $this->assertTrue(0 === $random->sequence(2));
        $this->assertTrue(0 === $random->sequence(3));
        $this->assertTrue(1 === $random->sequence(3));
        $this->assertTrue(0 === $random->sequence(4));
        $this->assertTrue(1 === $random->sequence(4));
    }
}
