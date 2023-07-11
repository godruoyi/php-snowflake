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

use Closure;
use Exception;
use Godruoyi\Snowflake\Converters\Base10ToBase2Converter;

class Snowflake
{
    public const MAX_TIMESTAMP_LENGTH = 41;

    public const MAX_DATACENTER_LENGTH = 5;

    public const MAX_WORKID_LENGTH = 5;

    public const MAX_SEQUENCE_LENGTH = 12;

    public const MAX_SEQUENCE_SIZE = (-1 ^ (-1 << self::MAX_SEQUENCE_LENGTH));

    /**
     * The data center id.
     */
    protected int $datacenter;

    /**
     * The worker id.
     */
    protected int $workerid;

    /**
     * The Sequence Resolver instance.
     */
    protected null|Closure|SequenceResolver $sequence = null;

    /**
     * The start timestamp.
     */
    protected ?int $startTime = null;

    /**
     * Default sequence resolver.
     */
    protected ?SequenceResolver $defaultSequenceResolver = null;

    /**
     * Build Snowflake Instance.
     */
    public function __construct(int $datacenter = -1, int $workerId = -1)
    {
        $maxDataCenter = -1 ^ (-1 << self::MAX_DATACENTER_LENGTH);
        $maxWorkId = -1 ^ (-1 << self::MAX_WORKID_LENGTH);

        // If not set datacenter or workid, we will set a default value to use.
        $this->datacenter = $datacenter > $maxDataCenter || $datacenter < 0 ? random_int(0, 31) : $datacenter;
        $this->workerid = $workerId > $maxWorkId || $workerId < 0 ? random_int(0, 31) : $workerId;
    }

    /**
     * Get snowflake id.
     */
    public function id(): string
    {
        $currentTime = $this->getCurrentMillisecond();
        while (($sequence = $this->callResolver($currentTime)) > (-1 ^ (-1 << self::MAX_SEQUENCE_LENGTH))) {
            usleep(1);
            $currentTime = $this->getCurrentMillisecond();
        }

        $workerLeftMoveLength = self::MAX_SEQUENCE_LENGTH;
        $datacenterLeftMoveLength = self::MAX_WORKID_LENGTH + $workerLeftMoveLength;
        $timestampLeftMoveLength = self::MAX_DATACENTER_LENGTH + $datacenterLeftMoveLength;

        return (string) ((($currentTime - $this->getStartTimeStamp()) << $timestampLeftMoveLength)
            | ($this->datacenter << $datacenterLeftMoveLength)
            | ($this->workerid << $workerLeftMoveLength)
            | ($sequence));
    }

    /**
     * Parse snowflake id.
     */
    public function parseId(string $id, bool $transform = false): array
    {
        $id = Base10ToBase2Converter::convert($id);

        $data = [
            'timestamp' => substr($id, 0, -22),
            'sequence' => substr($id, -12),
            'workerid' => substr($id, -17, 5),
            'datacenter' => substr($id, -22, 5),
        ];

        return $transform ? array_map(function ($value) {
            return bindec($value);
        }, $data) : $data;
    }

    /**
     * Get current millisecond time.
     */
    public function getCurrentMillisecond(): int
    {
        return floor(microtime(true) * 1000) | 0;
    }

    /**
     * Set start time (millisecond).
     *
     * @throws Exception
     */
    public function setStartTimeStamp(int $millisecond): self
    {
        $missTime = $this->getCurrentMillisecond() - $millisecond;

        if ($missTime < 0) {
            throw new Exception('The start time cannot be greater than the current time');
        }

        $maxTimeDiff = -1 ^ (-1 << self::MAX_TIMESTAMP_LENGTH);

        if ($missTime > $maxTimeDiff) {
            throw new PhpSnowflakeException(sprintf('The current microtime - starttime is not allowed to exceed -1 ^ (-1 << %d), You can reset the start time to fix this', self::MAX_TIMESTAMP_LENGTH));
        }

        $this->startTime = $millisecond;

        return $this;
    }

    /**
     * Get start timestamp (millisecond), If not set default to 2019-08-08 08:08:08.
     */
    public function getStartTimeStamp(): float|int
    {
        if (! is_null($this->startTime)) {
            return $this->startTime;
        }

        // We set a default start time if you not set.
        $defaultTime = '2019-08-08 08:08:08';

        return strtotime($defaultTime) * 1000;
    }

    /**
     * Set Sequence Resolver.
     */
    public function setSequenceResolver(callable|SequenceResolver $sequence): self
    {
        $this->sequence = $sequence;

        return $this;
    }

    /**
     * Get Sequence Resolver.
     */
    public function getSequenceResolver(): null|Closure|SequenceResolver
    {
        return $this->sequence;
    }

    /**
     * Get Default Sequence Resolver.
     */
    public function getDefaultSequenceResolver(): SequenceResolver
    {
        return $this->defaultSequenceResolver ?: $this->defaultSequenceResolver = new RandomSequenceResolver();
    }

    /**
     * Call resolver.
     */
    protected function callResolver(mixed $currentTime): int
    {
        $resolver = $this->getSequenceResolver();

        if (!is_null($resolver) && is_callable($resolver)) {
            return $resolver($currentTime);
        }

        return ! ($resolver instanceof SequenceResolver)
            ? $this->getDefaultSequenceResolver()->sequence($currentTime)
            : $resolver->sequence($currentTime);
    }
}
