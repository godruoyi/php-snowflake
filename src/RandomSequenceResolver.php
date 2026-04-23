<?php

declare(strict_types=1);

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Godruoyi\Snowflake;

use Exception;

class RandomSequenceResolver implements SequenceResolver
{
    /**
     * The last timestamp.
     */
    protected int $lastTimeStamp = -1;

    /**
     * The sequence.
     */
    protected int $sequence = 0;

    /**
     * Max sequence number in single ms.
     */
    protected int $maxSequence = Snowflake::MAX_SEQUENCE_SIZE;

    /**
     * Min sequence number in single ms.
     */
    protected int $minSequence = 0;

    /**
     * @throws Exception
     */
    public function sequence(int $currentTime): int
    {
        if ($this->lastTimeStamp === $currentTime) {
            $this->sequence++;
            $this->lastTimeStamp = $currentTime;

            return $this->sequence;
        }

        $range = max(1, $this->maxSequence - $this->minSequence);
        $this->sequence = $this->minSequence + (abs(crc32(uniqid((string) random_int(0, PHP_INT_MAX), true))) % $range);
        $this->lastTimeStamp = $currentTime;

        return $this->sequence;
    }

    public function setMaxSequence(int $maxSequence): void
    {
        $this->maxSequence = $maxSequence;
    }

    public function setMinSequence(int $minSequence): void
    {
        $this->minSequence = $minSequence;
    }
}
