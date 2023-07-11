<?php

declare(strict_types=1);

/*
 * This file is part of the godruoyi/php-snowflake.
 *
 * (c) Godruoyi <g@godruoyi.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Tests\Converters;

use Generator;
use Godruoyi\Snowflake\Converters\Base10ToBase2Converter;
use Godruoyi\Snowflake\Converters\NonNumericStringConversionException;
use Tests\TestCase;

final class Base10ToBase2ConverterTest extends TestCase
{
    public function testThrowsExceptionOnNonNumericNumber(): void
    {
        $this->expectException(NonNumericStringConversionException::class);

        Base10ToBase2Converter::convert('123abc');
    }

    /**
     * @dataProvider provideValidConversionNumbers
     */
    public function testConvertsNumbersProperly(string $base10, string $base2): void
    {
        $converted = Base10ToBase2Converter::convert($base10);

        $this->assertSame($base2, $converted);
    }

    public static function provideValidConversionNumbers(): Generator
    {
        yield '0' => ['0', '0'];

        yield '1' => ['1', '1'];

        yield '2' => ['2', '10'];

        yield 'over max 64 bits' => [
            '9018446744073709551615',
            '1111010001110010000010111000110111111010011010011100111111111111111111111',
        ];
    }
}
