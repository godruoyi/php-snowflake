<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests;

use Godruoyi\Snowflake\RandomSequenceResolver;
use Godruoyi\Snowflake\Snowflake;

class RandomSequenceResolverTest extends TestCase
{
    public function testBasic()
    {
        $random = new RandomSequenceResolver();
        $seqs = [];

        for ($i = 0; $i < Snowflake::MAX_SEQUENCE_SIZE; $i++) {
            $seqs[$random->sequence(0)] = true;
        }

        $this->assertCount(Snowflake::MAX_SEQUENCE_SIZE, $seqs);
    }

    public function testCanGenerateUniqueIdBySnowflake()
    {
        $snowflake = new Snowflake(1, 1);
        $seqs = [];

        for ($i = 0; $i < Snowflake::MAX_SEQUENCE_SIZE; $i++) {
            $seqs[$snowflake->id()] = true;
        }

        $this->assertCount(Snowflake::MAX_SEQUENCE_SIZE, $seqs);
    }
}
