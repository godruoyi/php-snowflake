<?php

namespace Godruoyi\Snowflake;

use Closure;
use Exception;

class FileLockResolver implements SequenceResolver
{
    public const SHARD_COUNT = 1;

    public static $openMode = 'a+';

    public static $lockOperation = LOCK_SH;

    /**
     * @var string
     */
    protected $lockFileDir;

    /**
     * @var resource[]
     */
    protected $shardLockMap = [];

    /**
     * @throws Exception
     */
    public function __construct(string $lockFileDir = null)
    {
        $this->lockFileDir = $this->preparePath($lockFileDir);

        $this->createShardLockFiles();
    }

    /**
     * {@inheritDoc}
     *
     * @throws Exception when can not open lock file.
     */
    public function sequence(int $currentTime)
    {
        $filePath = $this->getShardLockFile($currentTime);
        [$writer, $locked] = $this->locker($filePath);

        // lock failed, use microtime to generate sequence.
        if ($locked === false) {
            return Snowflake::MAX_SEQUENCE_SIZE + 1;
        }

        return $writer($currentTime);
    }

    /**
     * @see https://en.wikipedia.org/wiki/Fowler%E2%80%93Noll%E2%80%93Vo_hash_function
     *
     * @param  string  $str
     * @return int
     */
    public function fnv(string $str): int
    {
        $hash = 2166136261;

        for ($i = 0; $i < strlen($str); $i++) {
            $hash ^= ord($str[$i]);
            $hash *= 16777619;
        }

        return $hash;
    }

    /**
     * Shard lock file path.
     *
     * @param  int  $currentTime
     * @return string
     */
    public function getShardLockFile(int $currentTime): string
    {
        $index = $this->fnv($currentTime) % self::SHARD_COUNT;

        return $this->shardLockMap[$index];
    }

    /**
     * Delete all lock files.
     *
     * @return void
     */
    public function cleanAllLocks()
    {
        $files = glob(sprintf('%s/*.lock', $this->lockFileDir));

        foreach ($files as $file) {
            unlink($file);
        }
    }

    /**
     * @param  string  $file
     * @return array
     *
     * @throws Exception
     */
    protected function locker(string $file): array
    {
        $f = null;

        try {
            if (! file_exists($file)) {
                throw new Exception(sprintf('File %s is not exists.', $file));
            }

            $f = fopen($file, static::$openMode);
            if ($f === false || ! flock($f, static::$lockOperation)) {
                return [null, null, false];
            }

            return [$this->calculateSequence($f, $file), true];
        } catch (Exception $e) {
            $this->closeAndUnLock($f);

            throw new Exception('Can not open lock file: '.$file, $e->getCode(), $e);
        }
    }

    /**
     * Clean up the lock file.
     *
     * @param $f
     * @return void
     */
    protected function closeAndUnLock($f): void
    {
        if ($f && is_resource($f)) {
            flock($f, LOCK_UN);
            fclose($f);
        }
    }

    /**
     * Calculate sequence.
     *
     * @param  resource  $f
     * @param $path
     * @return Closure
     */
    protected function calculateSequence($f, $path): Closure
    {
        return function (int $k) use ($f, $path) {
            $content = file_get_contents($path);
            $data = [];

            if ($content) {
                $data = json_decode($content, true);
            }

            if (isset($data[$k])) {
                $data[$k] += 1;
            } else {
                $data[$k] = 1;
            }

            file_put_contents($path, json_encode($data));
            $this->closeAndUnLock($f);

            return $data[$k];
        };
    }

    /**
     * Check path is exists and writable.
     *
     * @throws Exception
     */
    protected function preparePath(?string $lockFileDir): string
    {
        if (empty($lockFileDir)) {
            $lockFileDir = dirname(__DIR__).'/.locks/';
        }

        if (! is_dir($lockFileDir)) {
            throw new Exception("{$lockFileDir} is not a directory.");
        }

        if (! is_writable($lockFileDir)) {
            throw new Exception("{$lockFileDir} is not writable.");
        }

        return $lockFileDir;
    }

    /**
     * Generate shard lock files.
     *
     * @return void
     */
    protected function createShardLockFiles()
    {
        $lockFile = '';

        for ($i = 0; $i < self::SHARD_COUNT; $i++) {
            $lockFile = sprintf('%s%ssnowflake-%s.lock', rtrim($this->lockFileDir, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR, $i);
            if (! file_exists($lockFile)) {
                touch($lockFile);
            }

            $this->shardLockMap[$i] = $lockFile;
        }

        // Wait for all lock files to be created
        while (stat($lockFile) === false) {
            usleep(1000);
        }
    }
}
