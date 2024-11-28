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

use Godruoyi\Snowflake\FileLockResolver;
use Godruoyi\Snowflake\SnowflakeException;

class FileLockResolverTest extends TestCase
{
    private static string $mainLockFileDirPath;
    private static string $unWriteableFileDirPath;

    private FileLockResolver $fileLocker;

    public static function setUpBeforeClass(): void
    {
        self::$mainLockFileDirPath = dirname(__DIR__) . '/.locks';
        self::$unWriteableFileDirPath = __DIR__ . '/.locks';
    }

    protected function setUp(): void
    {
        mkdir(self::$mainLockFileDirPath, 0777);
        mkdir(self::$unWriteableFileDirPath, 0444);

        $this->fileLocker = new FileLockResolver(self::$mainLockFileDirPath);
    }

    protected function tearDown(): void
    {
        $this->cleanUpLockFileDirs();
    }

    public function test_prepare_path(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(__FILE__.' is not a directory.');
        new FileLockResolver(__FILE__);
    }

    public function test_prepare_path_not_writable(): void
    {
        $resolver = new FileLockResolver('/tmp/');
        $this->assertEquals('/tmp/', $this->invokeProperty($resolver, 'lockFileDir'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(self::$unWriteableFileDirPath.' is not writable.');
        $resolver = new FileLockResolver(self::$unWriteableFileDirPath);
    }

    public function test_array_slice(): void
    {
        $a = [1, 2, 3, 4, 5, 6];

        $this->assertEquals([2 => 3, 3 => 4, 4 => 5, 5 => 6], array_slice($a, -4, null, true));

        $a = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6];

        $this->assertEquals(['c' => 3, 'd' => 4, 'e' => 5, 'f' => 6], array_slice($a, -4, null, true));
    }

    public function test_clean_old_sequence(): void
    {
        $resolver = $this->fileLocker;

        $a = ['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5, 'f' => 6];
        $d = $resolver->cleanOldSequences($a);
        $this->assertEquals($a, $d);

        FileLockResolver::$maxItems = 3;
        $d = $resolver->cleanOldSequences($a);

        $this->assertEquals(['d' => 4, 'e' => 5, 'f' => 6], $d);
    }

    public function test_increment_sequence_with_specify_time(): void
    {
        $resolver = $this->fileLocker;

        $this->assertEquals(['1' => 1], $resolver->incrementSequenceWithSpecifyTime([], 1));
        $this->assertEquals(['a' => 1, '1' => 1], $resolver->incrementSequenceWithSpecifyTime(['a' => 1], 1));
        $this->assertEquals(['1' => 2], $resolver->incrementSequenceWithSpecifyTime([1 => 1], 1));
        $this->assertEquals(['1' => 1, '2' => 1], $resolver->incrementSequenceWithSpecifyTime([1 => 1], 2));
    }

    public function test_get_contents_with_empty(): void
    {
        $resolver = $this->fileLocker;

        $path = $this->touch();
        $f = fopen($path, FileLockResolver::FileOpenMode);
        $content = $resolver->getContents($f);

        $this->assertIsArray($content);
        $this->assertEmpty($content);

        fclose($f);
        unlink($path);
    }

    public function test_get_contents_with_serialized_data(): void
    {
        $resolver = $this->fileLocker;
        $data = serialize(['a' => 1]);

        $path = $this->touch($data);
        $f = fopen($path, FileLockResolver::FileOpenMode);
        $content = $resolver->getContents($f);

        $this->assertIsArray($content);
        $this->assertArrayHasKey('a', $content);

        fclose($f);
        unlink($path);
    }

    public function test_get_contents_with_invalid_data(): void
    {
        $resolver = $this->fileLocker;

        $path = $this->touch('{"1":1}');
        $f = fopen($path, FileLockResolver::FileOpenMode);
        $content = $resolver->getContents($f);

        $this->assertIsNotArray($content);
        $this->assertNull($content);

        fclose($f);
        unlink($path);
    }

    public function test_update_contents(): void
    {
        $resolver = $this->fileLocker;
        $path = $this->touch();
        $f = fopen($path, FileLockResolver::FileOpenMode);

        $this->assertTrue($resolver->updateContents(['a' => 'a'], $f));

        $this->assertEquals(['a' => 'a'], unserialize(file_get_contents($path)));

        unlink($path);
    }

    public function test_get_sequence_file_not_exists(): void
    {
        $path = 'a/b/c/d/e/f';

        $time = 1;
        $resolver = $this->fileLocker;

        $this->expectException(\Exception::class);
        $this->invokeMethod($resolver, 'getSequence', [$path, $time]);
    }

    public function test_get_sequence_file_cannot_open_file(): void
    {
        $path = $this->touch();
        chmod($path, 0444);

        $time = 1;
        $resolver = $this->fileLocker;

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(sprintf('can not open/lock this file %s', $path));
        $this->invokeMethod($resolver, 'getSequence', [$path, $time]);

        unlink($path);
    }

    public function test_get_sequence_file_cannot_lock(): void
    {
        // @todo add test
        $this->assertTrue(true);
    }

    public function test_get_sequence_with_invalid_content(): void
    {
        $path = $this->touch('x');
        $time = 1;

        $resolver = $this->fileLocker;

        $this->expectException(\Exception::class);
        $this->invokeMethod($resolver, 'getSequence', [$path, $time]);

        unlink($path);
    }

    public function test_get_sequence(): void
    {
        $path = $this->touch();
        $time = 1;

        $resolver = $this->fileLocker;

        $this->invokeMethod($resolver, 'getSequence', [$path, $time]);
        $this->invokeMethod($resolver, 'getSequence', [$path, $time]);
        $this->invokeMethod($resolver, 'getSequence', [$path, $time]);
        $s = $this->invokeMethod($resolver, 'getSequence', [$path, $time]);
        $this->assertEquals(4, $s);

        $this->invokeMethod($resolver, 'getSequence', [$path, 2]);
        $this->invokeMethod($resolver, 'getSequence', [$path, 2]);
        $this->invokeMethod($resolver, 'getSequence', [$path, 2]);
        $s = $this->invokeMethod($resolver, 'getSequence', [$path, 2]);
        $this->assertEquals(4, $s);

        $this->assertEquals([1 => 4, 2 => 4], unserialize(file_get_contents($path)));

        unlink($path);
    }

    public function test_update_contents_with_content(): void
    {
        $resolver = $this->fileLocker;
        $data = ['a' => 1, 'c' => 3];
        $path = $this->touch(serialize($data));

        $f = fopen($path, FileLockResolver::FileOpenMode);

        $this->assertTrue($resolver->updateContents(['a' => 2, 'b' => 3], $f));

        $this->assertEquals(['a' => 2, 'b' => 3], unserialize(file_get_contents($path)));

        unlink($path);
    }

    public function test_fnv(): void
    {
        $resolver = $this->fileLocker;
        $a = $resolver->fnv('1674128900558');

        $this->assertSame(455874157.0, $a);
    }

    public function test_get_shard_lock_index(): void
    {
        // reset
        FileLockResolver::$shardCount = 1;

        $resolver = $this->fileLocker;
        $index = $resolver->getShardLockIndex(1);
        $this->assertTrue($index >= 0 && $index < FileLockResolver::$shardCount);

        $index2 = $resolver->getShardLockIndex(99999999999);
        $this->assertTrue($index >= 0 && $index < FileLockResolver::$shardCount);

        $this->assertEquals($index, $index2);
    }

    public function test_create_shard_lock_file_with_not_exists_path(): void
    {
        $resolver = $this->fileLocker;
        $index = 1;

        $path = $this->invokeMethod($resolver, 'createShardLockFile', [$index]);
        $this->assertFileExists($path);
        $this->assertEquals('snowflake-1.lock', pathinfo($path)['basename']);

        unlink($path);
    }

    public function test_create_shard_lock_file_with_exists_path(): void
    {
        $resolver = $this->fileLocker;
        $index = 1;

        $path = $this->invokeMethod($resolver, 'filePath', [$index]);
        $this->assertTrue(! file_exists($path));

        touch($path);

        $path = $this->invokeMethod($resolver, 'createShardLockFile', [$index]);
        $this->assertFileExists($path);
        $this->assertEquals('snowflake-1.lock', pathinfo($path)['basename']);

        unlink($path);
    }

    public function test_file_path(): void
    {
        $resolver = $this->fileLocker;
        $index = 1;

        $path = $this->invokeMethod($resolver, 'filePath', [$index]);
        $this->assertTrue(! file_exists($path));
    }

    public function test_sequence(): void
    {
        $resolver = $this->fileLocker;
        $resolver->cleanAllLocksFile();

        $this->assertEquals(1, $resolver->sequence(1));
        $this->assertEquals(2, $resolver->sequence(1));
        $this->assertEquals(3, $resolver->sequence(1));
        $this->assertEquals(4, $resolver->sequence(1));
        $this->assertEquals(1, $resolver->sequence(2));
        $this->assertEquals(1, $resolver->sequence(3));
    }

    public function test_sequence_with_max_items(): void
    {
        // only one lock file will be generated
        FileLockResolver::$shardCount = 1;
        FileLockResolver::$maxItems = 3;

        $resolver = $this->fileLocker;
        $resolver->cleanAllLocksFile();

        $this->assertEquals(1, $resolver->sequence(1));
        $this->assertEquals(1, $resolver->sequence(2));
        $this->assertEquals(1, $resolver->sequence(3));

        // the first one will be removed
        $this->assertEquals(1, $resolver->sequence(4));
        // so when we get the snowflake again we will get 0
        $this->assertEquals(1, $resolver->sequence(1));
    }

    public function test_preg_match(): void
    {
        $resolver = $this->fileLocker;
        $index = 1;
        $path = $this->invokeMethod($resolver, 'filePath', [$index]);

        $this->assertTrue(preg_match('/snowflake-(\d+)\.lock$/', $path) !== false);
    }

    /**
     * @throws SnowflakeException
     */
    public function test_can_clean_lock_file(): void
    {
        FileLockResolver::$shardCount = 1;
        $fileResolver = $this->fileLocker;

        // this operation will generate a lock file
        $fileResolver->sequence(1);

        $path = $this->invokeMethod($fileResolver, 'filePath', [0]);

        $this->assertFileExists($path);

        $fileResolver->cleanAllLocksFile();

        $this->assertFileDoesNotExist($path);
    }

    private function touch(string $content = ''): string
    {
        $file = tempnam(self::$mainLockFileDirPath, 'snowflake');

        if ($file === false) {
            throw new \RuntimeException('Unable to create file');
        }

        if ($content !== '') {
            file_put_contents($file, $content);
        }

        return $file;
    }

    private function cleanUpLockFileDirs(): void
    {
        $glob = self::$mainLockFileDirPath . '/*';
        $files = glob($glob);
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir(self::$mainLockFileDirPath);
        rmdir(self::$unWriteableFileDirPath);
    }
}
