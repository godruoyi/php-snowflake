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

use Godruoyi\Snowflake\RandomSequenceResolver;
use Godruoyi\Snowflake\Snowflake;

class RandomSequenceResolverTest extends TestCase
{
    public function test_basic(): void
    {
        $random = new RandomSequenceResolver();
        $seqs = [];

        for ($i = 0; $i < Snowflake::MAX_SEQUENCE_SIZE; $i++) {
            $seqs[$random->sequence(0)] = true;
        }

        $this->assertCount(Snowflake::MAX_SEQUENCE_SIZE, $seqs);
    }

    public function test_can_generate_unique_id_using_same_instance(): void
    {
        $snowflake = new Snowflake(1, 1);
        $seqs = [];

        for ($i = 0; $i < Snowflake::MAX_SEQUENCE_SIZE; $i++) {
            $seqs[$snowflake->id()] = true;
        }

        $this->assertCount(Snowflake::MAX_SEQUENCE_SIZE, $seqs);
    }

    public function test_can_generate_unique_id_by_different_instance(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $ids = [];

            for ($i = 0; $i < 100_000; $i++) {
                $snowflake = new Snowflake();
                $ids[$snowflake->id()] = true;
            }

            // We expect to have at least 100_000 - 10 unique ids if using random sequence resolver
            $this->assertGreaterThanOrEqual(100_000 - 10, count($ids));
        }
    }
}
