<div>
  <p align="center">
    <image src="https://www.pngkey.com/png/full/105-1052235_snowflake-png-transparent-background-snowflake-with-clear-background.png" width="250" height="250">
  </p>
  <p align="center">An ID Generator for PHP based on Snowflake Algorithm (Twitter announced).</p>
  <p align="center">
    <a href="https://scrutinizer-ci.com/g/godruoyi/php-snowflake/">
      <image src="https://scrutinizer-ci.com/g/godruoyi/php-snowflake/badges/quality-score.png?b=master" alt="quality score">
    </a>
    <a href="https://scrutinizer-ci.com/g/godruoyi/php-snowflake/">
      <image src="https://scrutinizer-ci.com/g/godruoyi/php-snowflake/badges/coverage.png?b=master" alt="php-snowflake">
    </a>
    <a href="https://github.com/godruoyi/php-snowflake">
      <image src="https://poser.pugx.org/godruoyi/php-snowflake/license" alt="License">
    </a>
    <a href="https://packagist.org/packages/godruoyi/php-snowflake">
      <image src="https://poser.pugx.org/godruoyi/php-snowflake/v/stable" alt="Packagist Version">
    </a>
    <a href="https://packagist.org/packages/godruoyi/php-snowflake">
      <image src="https://scrutinizer-ci.com/g/godruoyi/php-snowflake/badges/build.png?b=master" alt="build passed">
    </a>
    <a href="https://packagist.org/packages/godruoyi/php-snowflake">
      <image src="https://poser.pugx.org/godruoyi/php-snowflake/downloads" alt="Total Downloads">
    </a>
  </p>
</div>

## Description



## Requirement

1. PHP >= 7.0
2. **[Composer](https://getcomposer.org/)**

## Installation

```shell
$ composer require godruoyi/php-snowflake -vvv
```

## Usage

```php
$snowflake = new \Godruoyi\Snowflake\Snowflake;

$snowflake->id();
// 1537200202186752
```

## Advanced

1. custom start timestamp.

```php
$snowflake = new \Godruoyi\Snowflake\Snowflake;
$snowflake->setStartTimeStamp(strtotime('2019-09-09')*1000)->id();
```

2. custom sequence resolver.

```php
$snowflake = new \Godruoyi\Snowflake\Snowflake;
$snowflake->setSequenceResolver(function ($currentTime) {
    static $lastTime;
    static $sequence;

    if ($lastTime == $currentTime) {
        ++$sequence;
    }

    $lastTime = $currentTime;

    return $sequence;
})->id();
```

## License

MIT