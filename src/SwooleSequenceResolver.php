<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Godruoyi\Snowflake;

class SwooleSequenceResolver implements SequenceResolver
{
    /**
     * The las ttimestamp.
     *
     * @var null
     */
    protected $lastTimeStamp = -1;

    /**
     * The sequence.
     *
     * @var int
     */
    protected $sequence = 0;

    /**
     * The swoole lock.
     *
     * @var mixed
     */
    protected $lock;

    /**
     * The cycle count.
     *
     * @var int
     */
    protected $count = 0;

    /**
     * Init swoole lock.
     */
    public function __construct()
    {
        $this->lock = new \Swoole\Lock(SWOOLE_MUTEX);
    }

    /**
     *  {@inheritdoc}
     */
    public function sequence(int $currentTime)
    {
        // If swoole lock failure，we will return a big number, and recall this method when next millisecond.
        if (!$this->lock->trylock()) {
            if ($this->count >= 10) {
                throw new \Exception('Swoole lock failure, Unable to get the program lock after many attempts.');
            }

            ++$this->count;

            // return a big number
            return 999999;
        }

        if ($this->lastTimeStamp === $currentTime) {
            ++$this->sequence;
        } else {
            $this->sequence = 0;
        }

        $this->lastTimeStamp = $currentTime;

        $this->lock->unlock();

        return $this->sequence;
    }
}
