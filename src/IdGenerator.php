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

interface IdGenerator
{
    /**
     * Get snowflake id.
     *
     * @throws SnowflakeException
     */
    public function id(): string;

    /**
     * Get snowflake id for specific timestamp.
     *
     * @throws SnowflakeException
     */
    public function idFor(\DateTime|int $timestamp): string;

    /**
     * Parse snowflake id.
     *
     * @return array<string, float|int|string>
     */
    public function parseId(string $id, bool $transform = false): array;

    /**
     * Calculate the unix timestamp from a given timestamp relative to the start time.
     */
    public function toMicrotime(int $timestamp): float|int;

    /**
     * Get current millisecond time.
     */
    public function getCurrentMillisecond(): int;

    /**
     * Set start time (millisecond).
     *
     * @throws SnowflakeException
     */
    public function setStartTimeStamp(int $millisecond): self;

    /**
     * Get start timestamp (millisecond), If not set default to 2019-08-08 08:08:08.
     */
    public function getStartTimeStamp(): float|int;

    /**
     * Set Sequence Resolver.
     */
    public function setSequenceResolver(Closure|SequenceResolver $sequence): self;

    /**
     * Get Sequence Resolver.
     */
    public function getSequenceResolver(): null|Closure|SequenceResolver;

    /**
     * Get Default Sequence Resolver.
     */
    public function getDefaultSequenceResolver(): SequenceResolver;
}
