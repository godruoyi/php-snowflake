<?php

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Godruoyi\Snowflake;

class RandomSequenceResolver implements SequenceResolver
{
    /**
     * The last timestamp.
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
     * Max sequence number in one ms.
     *
     * @var int
     */
    protected $maxSequence = Snowflake::MAX_SEQUENCE_SIZE;

    /**
     *  {@inheritdoc}
     */
    public function sequence(int $currentTime)
    {
        if ($this->lastTimeStamp === $currentTime) {
            $this->sequence++;
            $this->lastTimeStamp = $currentTime;

            return $this->sequence;
        }

        $this->sequence = mt_rand(0, $this->maxSequence);
        $this->lastTimeStamp = $currentTime;

        return $this->sequence;
    }

    /**
     * @param  int  $maxSequence
     */
    public function setMaxSequence(int $maxSequence): void
    {
        $this->maxSequence = $maxSequence;
    }
}
