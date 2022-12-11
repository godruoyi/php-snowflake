<?php

namespace Tests;

use Godruoyi\Snowflake\FileLockResolver;

class FileLockResolverTest extends TestCase
{
    public function test_prepare_path()
    {
        $resolver = new FileLockResolver();
        $this->assertEquals(dirname(__DIR__).'/.locks/', $this->invokeProperty($resolver, 'lockFileDir'));
        $this->assertCount(FileLockResolver::SHARD_COUNT, $this->invokeProperty($resolver, 'shardLockMap'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(__FILE__.' is not a directory.');
        $resolver = new FileLockResolver(__FILE__);
    }

    public function test_prepare_path_not_writable()
    {
        $resolver = new FileLockResolver('/tmp/');
        $this->assertEquals('/tmp/', $this->invokeProperty($resolver, 'lockFileDir'));

        $dir = __DIR__.'/.locks/';
        if (! is_dir($dir)) {
            mkdir($dir, 0444);
        }

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage($dir.' is not writable.');
        $resolver = new FileLockResolver($dir);

        rmdir($dir);
    }

    public function test_can_get_shard_lock_file()
    {
        $resolver = new FileLockResolver;
        $file = $resolver->getShardLockFile(1);

        $this->assertFileExists($file);
    }

    public function test_locker_failed_cannot_open_file_exception()
    {
        $resolver = new FileLockResolver;

        $this->expectException(\Exception::class);
        $this->invokeMethod($resolver, 'locker', ['aa1']);
    }

    public function test_locker_success()
    {
        $resolver = new FileLockResolver;
        $file = $resolver->getShardLockFile(1);

        [$fn, $ok] = $this->invokeMethod($resolver, 'locker', [$file]);

        $this->assertTrue($ok);
        $this->assertInstanceOf(\Closure::class, $fn);
    }

    public function test_calculate_sequence()
    {
        $resolver = new FileLockResolver;
        $file = $resolver->getShardLockFile(1);
        $f = fopen($file, 'r+');
        ftruncate($f, 0);

        $fn = $this->invokeMethod($resolver, 'calculateSequence', [$f, $file]);
        $this->assertInstanceOf(\Closure::class, $fn);

        $this->assertEquals(1, $fn(1));
        $this->assertEquals(json_encode([1 => 1]), file_get_contents($file));
        $resolver->cleanAllLocks();
    }

    public function test_sequence()
    {
        $resolver = new FileLockResolver;

        $seq = $resolver->sequence(1);
        $this->assertEquals(1, $seq);
        $seq = $resolver->sequence(1);
        $this->assertEquals(2, $seq);

        $seq = $resolver->sequence(2);
        $this->assertEquals(1, $seq);
        $seq = $resolver->sequence(2);
        $this->assertEquals(2, $seq);

        $resolver->cleanAllLocks();
    }

    public function test_sequence_batch()
    {
        $hp = [];

        for ($i = 0; $i < 10000; $i++) {
            $resolver = new FileLockResolver;
            $seq = $resolver->sequence(1);
            $hp[$seq] = 1;
        }

        $this->assertCount(10000, $hp);
    }
}
