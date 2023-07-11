<?php

declare(strict_types=1);

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Godruoyi\Snowflake\Converters;

final class Base10ToBase2Converter
{
    /**
     * @throws NonNumericStringConversionException
     */
    public static function convert(string $number): string
    {
        if (! is_numeric($number)) {
            throw new NonNumericStringConversionException('Input string must contain only numeric characters');
        }

        if ($number === '0') {
            return '0';
        }

        $base2String = '';
        $dividend = $number;

        while (bccomp($dividend, '0') > 0) {
            $remainder = bcmod($dividend, '2');
            $dividend = bcdiv($dividend, '2');
            $base2String = $remainder.$base2String;
        }

        return $base2String;
    }
}
