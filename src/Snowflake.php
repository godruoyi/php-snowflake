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
use DateTimeInterface;

class Snowflake
{
    public const MAX_TIMESTAMP_LENGTH = 41;

    public const MAX_DATACENTER_LENGTH = 5;

    public const MAX_WORKID_LENGTH = 5;

    public const MAX_SEQUENCE_LENGTH = 12;

    public const MAX_SEQUENCE_SIZE = (-1 ^ (-1 << self::MAX_SEQUENCE_LENGTH));

    /**
     * Drift algorithm: when the sequence overflows within a millisecond, the timestamp
     * is incremented by 1 (borrowing a future millisecond) instead of waiting for the
     * real clock to advance. This maximises throughput without blocking.
     */
    public const DRIFT_METHOD = 1;

    /**
     * Traditional algorithm: when the sequence overflows, the generator waits (spins)
     * until the real clock moves to the next millisecond before continuing.
     */
    public const TRADITIONAL_METHOD = 2;

    /**
     * The ID generation method (DRIFT_METHOD or TRADITIONAL_METHOD, default: DRIFT_METHOD).
     */
    protected int $method = self::DRIFT_METHOD;

    /**
     * The worker ID bit length (configurable, default: MAX_WORKID_LENGTH).
     */
    protected int $workerIdBitLength = self::MAX_WORKID_LENGTH;

    /**
     * The datacenter bit length (configurable, default: MAX_DATACENTER_LENGTH).
     */
    protected int $datacenterBitLength = self::MAX_DATACENTER_LENGTH;

    /**
     * The sequence number bit length (configurable, default: MAX_SEQUENCE_LENGTH).
     */
    protected int $sequenceBitLength = self::MAX_SEQUENCE_LENGTH;

    /**
     * The maximum sequence number per millisecond (0 = use max from bit length).
     */
    protected int $maxSequenceNumber = 0;

    /**
     * The minimum sequence number per millisecond.
     */
    protected int $minSequenceNumber = 0;

    /**
     * The data center id.
     */
    protected int $datacenter;

    /**
     * The worker id.
     */
    protected int $workerId;

    /**
     * The Sequence Resolver instance.
     */
    protected SequenceResolver|null|Closure $sequence = null;

    /**
     * The start timestamp.
     */
    protected ?int $startTime = null;

    /**
     * Default sequence resolver.
     */
    protected ?SequenceResolver $defaultSequenceResolver = null;

    /**
     * The last timestamp used to generate an ID (tracks drift to prevent duplicate timestamps).
     */
    protected int $lastTimestamp = 0;

    /**
     * Build Snowflake Instance.
     */
    public function __construct(int $datacenter = -1, int $workerId = -1)
    {
        $maxDataCenter = -1 ^ (-1 << self::MAX_DATACENTER_LENGTH);
        $maxWorkId = -1 ^ (-1 << self::MAX_WORKID_LENGTH);

        // If not set datacenter or workid, we will set a default value to use.
        $this->datacenter = $datacenter > $maxDataCenter || $datacenter < 0 ? random_int(0, 31) : $datacenter;
        $this->workerId = $workerId > $maxWorkId || $workerId < 0 ? random_int(0, 31) : $workerId;
    }

    /**
     * Get snowflake id.
     */
    public function id(): string
    {
        // Start from the greater of the real clock or the last used timestamp so that
        // IDs are always monotonically increasing even after a drift run.
        $currentTime = max($this->getCurrentMillisecond(), $this->lastTimestamp);

        if ($this->method === self::DRIFT_METHOD) {
            // Drift algorithm: borrow future milliseconds on sequence overflow (no blocking).
            while (($sequence = $this->callResolver($currentTime)) > $this->getMaxSequenceNumber()) {
                $currentTime++;
            }
        } else {
            // Traditional algorithm: wait for the real clock to advance on sequence overflow.
            while (($sequence = $this->callResolver($currentTime)) > $this->getMaxSequenceNumber()) {
                usleep(1);
                $currentTime = max($this->getCurrentMillisecond(), $this->lastTimestamp);
            }
        }

        $this->lastTimestamp = $currentTime;

        return $this->buildId($currentTime, $this->getStartTimeStamp(), $sequence);
    }

    /**
     * Generate snowflake id for a specific timestamp.
     *
     * @param  int|DateTimeInterface $timestamp The timestamp to generate ID for (in milliseconds or DateTime object)
     * @return string
     * @throws SnowflakeException
     */
    public function idForTimestamp(int|DateTimeInterface $timestamp): string
    {
        $currentTime = $timestamp instanceof DateTimeInterface
            ? (int) $timestamp->format('Uv')
            : $timestamp;

        // Validate timestamp is not earlier than start time
        $startTime = $this->getStartTimeStamp();
        if ($currentTime < $startTime) {
            throw new SnowflakeException('The provided timestamp cannot be earlier than the start time');
        }

        // Get sequence number (auto-increment if overflow)
        while (($sequence = $this->callResolver($currentTime)) > $this->getMaxSequenceNumber()) {
            $currentTime++;
        }

        return $this->buildId($currentTime, $startTime, $sequence);
    }

    protected function buildId(int $currentTime, float|int $startTime, int $sequence): string
    {
        $workerLeftMoveLength = $this->sequenceBitLength;
        $datacenterLeftMoveLength = $this->workerIdBitLength + $workerLeftMoveLength;
        $timestampLeftMoveLength = $this->datacenterBitLength + $datacenterLeftMoveLength;

        return (string) ((($currentTime - $startTime) << $timestampLeftMoveLength)
            | ($this->datacenter << $datacenterLeftMoveLength)
            | ($this->workerId << $workerLeftMoveLength)
            | ($sequence));
    }

    /**
     * Parse snowflake id.
     *
     * @return array<string, float|int|string>
     */
    public function parseId(string $id, bool $transform = false): array
    {
        $id = decbin((int) $id);

        $seqLen = $this->sequenceBitLength;
        $workerLen = $this->workerIdBitLength;
        $dcLen = $this->datacenterBitLength;
        $workerAndSeqLen = $workerLen + $seqLen;
        $totalLen = $dcLen + $workerAndSeqLen;

        $data = [
            'timestamp' => substr($id, 0, -$totalLen),
            'sequence' => substr($id, -$seqLen),
            'workerid' => substr($id, -$workerAndSeqLen, $workerLen),
            'datacenter' => substr($id, -$totalLen, $dcLen),
        ];

        return $transform ? array_map(static function ($value) {
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
     * @throws SnowflakeException
     */
    public function setStartTimeStamp(int $millisecond): self
    {
        $missTime = $this->getCurrentMillisecond() - $millisecond;

        if ($missTime < 0) {
            throw new SnowflakeException('The start time cannot be greater than the current time');
        }

        $maxTimeDiff = -1 ^ (-1 << self::MAX_TIMESTAMP_LENGTH);

        if ($missTime > $maxTimeDiff) {
            throw new SnowflakeException(sprintf(
                'The current microtime - starttime is not allowed to exceed -1 ^ (-1 << %d), You can reset the start time to fix this',
                self::MAX_TIMESTAMP_LENGTH
            ));
        }

        $this->startTime = $millisecond;

        return $this;
    }

    /**
     * Get start timestamp (millisecond), If not set default to 2019-08-08 08:08:08.
     */
    public function getStartTimeStamp(): float|int
    {
        if (!is_null($this->startTime)) {
            return $this->startTime;
        }

        // We set a default start time if you not set.
        $defaultTime = '2019-08-08 08:08:08';

        return strtotime($defaultTime) * 1000;
    }

    /**
     * Set Sequence Resolver.
     */
    public function setSequenceResolver(Closure|SequenceResolver $sequence): self
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
        if ($this->defaultSequenceResolver) {
            return $this->defaultSequenceResolver;
        }

        $resolver = new RandomSequenceResolver();
        $resolver->setMaxSequence($this->getMaxSequenceNumber());
        $resolver->setMinSequence($this->getMinSequenceNumber());

        return $this->defaultSequenceResolver = $resolver;
    }

    /**
     * Set the worker ID bit length.
     *
     * @throws SnowflakeException
     */
    public function setWorkerIdBitLength(int $length): self
    {
        if ($length < 0) {
            throw new SnowflakeException('WorkerIdBitLength must be a non-negative integer');
        }

        if ($this->datacenterBitLength + $length + $this->sequenceBitLength > 62) {
            throw new SnowflakeException('The sum of datacenterBitLength, workerIdBitLength, and sequenceBitLength must not exceed 62');
        }

        $this->workerIdBitLength = $length;
        $this->defaultSequenceResolver = null;

        return $this;
    }

    /**
     * Get the worker ID bit length.
     */
    public function getWorkerIdBitLength(): int
    {
        return $this->workerIdBitLength;
    }

    /**
     * Set the datacenter bit length.
     *
     * @throws SnowflakeException
     */
    public function setDatacenterBitLength(int $length): self
    {
        if ($length < 0) {
            throw new SnowflakeException('DatacenterBitLength must be a non-negative integer');
        }

        if ($length + $this->workerIdBitLength + $this->sequenceBitLength > 62) {
            throw new SnowflakeException('The sum of datacenterBitLength, workerIdBitLength, and sequenceBitLength must not exceed 62');
        }

        $this->datacenterBitLength = $length;
        $this->defaultSequenceResolver = null;

        return $this;
    }

    /**
     * Get the datacenter bit length.
     */
    public function getDatacenterBitLength(): int
    {
        return $this->datacenterBitLength;
    }

    /**
     * Set the sequence number bit length.
     *
     * The only hard constraint is that datacenterBitLength + workerIdBitLength + length ≤ 62.
     * Very small values (e.g. 1–2 bits) severely limit throughput per millisecond; the caller
     * is responsible for choosing a value appropriate for the expected load.
     *
     * @throws SnowflakeException
     */
    public function setSequenceBitLength(int $length): self
    {
        if ($length < 1) {
            throw new SnowflakeException('SequenceBitLength must be at least 1');
        }

        if ($this->datacenterBitLength + $this->workerIdBitLength + $length > 62) {
            throw new SnowflakeException('The sum of datacenterBitLength, workerIdBitLength, and sequenceBitLength must not exceed 62');
        }

        $this->sequenceBitLength = $length;
        $this->defaultSequenceResolver = null;

        return $this;
    }

    /**
     * Get the sequence number bit length.
     */
    public function getSequenceBitLength(): int
    {
        return $this->sequenceBitLength;
    }

    /**
     * Set the maximum sequence number per millisecond.
     * Use 0 to automatically use the maximum value for the configured sequence bit length.
     *
     * @throws SnowflakeException
     */
    public function setMaxSequenceNumber(int $max): self
    {
        if ($max < 0) {
            throw new SnowflakeException('MaxSequenceNumber must be a non-negative integer');
        }

        $maxFromBitLength = -1 ^ (-1 << $this->sequenceBitLength);
        if ($max > $maxFromBitLength) {
            throw new SnowflakeException(sprintf(
                'MaxSequenceNumber must not exceed %d (2^%d - 1) for the current sequence bit length',
                $maxFromBitLength,
                $this->sequenceBitLength
            ));
        }

        if ($max > 0 && $max <= $this->minSequenceNumber) {
            throw new SnowflakeException('MaxSequenceNumber must be greater than MinSequenceNumber');
        }

        $this->maxSequenceNumber = $max;
        $this->defaultSequenceResolver = null;

        return $this;
    }

    /**
     * Get the effective maximum sequence number per millisecond.
     */
    public function getMaxSequenceNumber(): int
    {
        return $this->maxSequenceNumber > 0
            ? $this->maxSequenceNumber
            : (-1 ^ (-1 << $this->sequenceBitLength));
    }

    /**
     * Set the minimum sequence number per millisecond.
     *
     * @throws SnowflakeException
     */
    public function setMinSequenceNumber(int $min): self
    {
        if ($min < 0) {
            throw new SnowflakeException('MinSequenceNumber must be a non-negative integer');
        }

        if ($this->maxSequenceNumber > 0 && $min >= $this->maxSequenceNumber) {
            throw new SnowflakeException('MinSequenceNumber must be less than MaxSequenceNumber');
        }

        $this->minSequenceNumber = $min;
        $this->defaultSequenceResolver = null;

        return $this;
    }

    /**
     * Get the minimum sequence number per millisecond.
     */
    public function getMinSequenceNumber(): int
    {
        return $this->minSequenceNumber;
    }

    /**
     * Set the ID generation method.
     *
     * - Snowflake::DRIFT_METHOD (1): on sequence overflow, borrow the next millisecond
     *   by incrementing the timestamp instead of waiting for the real clock (default).
     * - Snowflake::TRADITIONAL_METHOD (2): on sequence overflow, spin-wait until the
     *   real clock advances to the next millisecond.
     *
     * @throws SnowflakeException
     */
    public function setMethod(int $method): self
    {
        if ($method !== self::DRIFT_METHOD && $method !== self::TRADITIONAL_METHOD) {
            throw new SnowflakeException(sprintf(
                'Method must be %d (drift) or %d (traditional)',
                self::DRIFT_METHOD,
                self::TRADITIONAL_METHOD
            ));
        }

        $this->method = $method;

        return $this;
    }

    /**
     * Get the current ID generation method.
     */
    public function getMethod(): int
    {
        return $this->method;
    }

    /**
     * Call resolver.
     */
    protected function callResolver(int $currentTime): int
    {
        $resolver = $this->getSequenceResolver();

        if (!is_null($resolver) && is_callable($resolver)) {
            return $resolver($currentTime);
        }

        return !($resolver instanceof SequenceResolver)
            ? $this->getDefaultSequenceResolver()->sequence($currentTime)
            : $resolver->sequence($currentTime);
    }
}
