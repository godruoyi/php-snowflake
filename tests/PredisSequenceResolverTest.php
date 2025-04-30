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

use Godruoyi\Snowflake\PredisSequenceResolver;
use PHPUnit\Framework\MockObject\Exception;
use Predis\Client;
use ReflectionException;

class PredisSequenceResolverTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists('Predis\\Client')) {
            $this->markTestSkipped('Predis extension is not installed');
        }
    }

    /**
     * @throws ReflectionException
     * @throws Exception
     */
    public function test_set_cache_prefix(): void
    {
        $redis = $this->createMock(Client::class);
        $snowflake = new PredisSequenceResolver($redis);
        $snowflake->setCachePrefix('foo');

        $this->assertEquals('foo', $this->invokeProperty($snowflake, 'prefix'));
    }

    /**
     * Test order sequence
     *
     * @throws Exception
     */
    public function test_predis_sequence(): void
    {
        $redis = $this->createMock(Client::class);
        $redis->expects($this->exactly(4))
            ->method('__call')
            ->withAnyParameters()
            ->willReturn(1, 2, 3, 4);

        $snowflake = new PredisSequenceResolver($redis);

        $this->assertEquals(1, $snowflake->sequence(1));
        $this->assertEquals(2, $snowflake->sequence(1));
        $this->assertEquals(3, $snowflake->sequence(1));
        $this->assertEquals(4, $snowflake->sequence(1));
    }

    public function test_real_redis_connect(): void
    {
        if (! ($host = getenv('REDIS_HOST')) || ! ($port = getenv('REDIS_PORT'))) {
            $this->markTestSkipped('Redis host or port is not set, skip real redis test.');
        }

        $client = new Client([
            'scheme' => 'tcp',
            'host' => $host,
            'port' => $port | 0,
        ]);

        $client->ping();

        $randomKey = random_int(0, 99999);

        $redisResolver = new PredisSequenceResolver($client);

        $this->assertEquals(0, $redisResolver->sequence($randomKey));
        $this->assertEquals(1, $redisResolver->sequence($randomKey));
        $this->assertEquals(2, $redisResolver->sequence($randomKey));
        $this->assertEquals(3, $redisResolver->sequence($randomKey));

        sleep(11);

        $this->assertEquals(0, $redisResolver->sequence($randomKey));
    }
}
