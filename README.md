<div>
  <p align="center">
    <image src="https://www.pngkey.com/png/full/105-1052235_snowflake-png-transparent-background-snowflake-with-clear-background.png" width="250" height="250"></image>
  </p>
  <p align="center">An ID Generator for PHP based on Snowflake Algorithm (Twitter announced).</p>
  <p align="center">
    <a href="https://github.com/godruoyi/php-snowflake/actions/workflows/php.yml">
      <image src="https://github.com/godruoyi/php-snowflake/actions/workflows/php.yml/badge.svg" alt="build passed"></image>
    </a>
    <a href="https://codecov.io/gh/godruoyi/php-snowflake">
      <img src="https://codecov.io/gh/godruoyi/php-snowflake/branch/master/graph/badge.svg?token=7AAOYCJK97" alt=""/>
    </a>
    <a href="https://github.com/godruoyi/php-snowflake">
      <image src="https://poser.pugx.org/godruoyi/php-snowflake/license" alt="License"></image>
    </a>
    <a href="https://packagist.org/packages/godruoyi/php-snowflake">
      <image src="https://poser.pugx.org/godruoyi/php-snowflake/v/stable" alt="Packagist Version"></image>
    </a>
    <a href="https://github.com/godruoyi/php-snowflake">
      <image src="https://poser.pugx.org/godruoyi/php-snowflake/downloads" alt="Total Downloads"></image>
    </a>
  </p>
</div>

## Description

Snowflake algorithm PHP implementation [中文文档](https://github.com/godruoyi/php-snowflake/blob/master/README-zh_CN.md).

![file](https://images.godruoyi.com/logos/201908/13/_1565672621_LPW65Pi8cG.png)

Snowflake is a network service for generating unique ID numbers at high scale with some simple guarantees.

* The first bit is unused sign bit.
* The second part consists of a 41-bit timestamp (milliseconds) whose value is the offset of the current time relative to a certain time.
* The 5 bits of the third and fourth parts represent data center and worker, and max value is 2^5 -1 = 31.
* The last part consists of 12 bits, its means the length of the serial number generated per millisecond per working node, a maximum of 2^12 -1 = 4095 IDs can be generated in the same millisecond.
* In a distributed environment, five-bit datacenter and worker mean that can deploy 31 datacenters, and each datacenter can deploy up to 31 nodes.
* The binary length of 41 bits is at most 2^41 -1 millisecond = 69 years. So the snowflake algorithm can be used for up to 69 years, In order to maximize the use of the algorithm, you should specify a start time for it.

> You must know, The ID generated by the snowflake algorithm is not guaranteed to be unique.
> For example, when two different requests enter the same node of the same data center at the same time, and the sequence generated by the node is the same, the generated ID will be duplicated.

So if you want to use the snowflake algorithm to generate unique ID, You must ensure: The sequence-number generated in the same millisecond of the same node is unique.
Based on this, we created this package and integrated multiple sequence-number providers into it.

* RandomSequenceResolver (Random)
* RedisSequenceResolver (based on redis psetex and incrby)
* LaravelSequenceResolver (based on redis psetex and incrby)
* SwooleSequenceResolver (based on swoole_lock)

Each provider only needs to ensure that the serial number generated in the same millisecond is different. You can get a unique ID.

> **Warning**
> The RandomSequenceResolver does not guarantee that the generated IDs are unique, If you want to generate a unique ID, please use another resolver instead.

## Requirement

1. PHP >= 7.2
2. **[Composer](https://getcomposer.org/)**

## Installation

```shell
$ composer require godruoyi/php-snowflake -vvv
```

## Useage

1. simple to use.

```php
$snowflake = new \Godruoyi\Snowflake\Snowflake;

$snowflake->id();
// 1537200202186752
```

2. Specify the data center ID and machine ID.

```php
$snowflake = new \Godruoyi\Snowflake\Snowflake($datacenterId, $workerId);

$snowflake->id();
```

3. Specify start time.

```php
$snowflake = new \Godruoyi\Snowflake\Snowflake;
$snowflake->setStartTimeStamp(strtotime('2019-09-09')*1000); // millisecond

$snowflake->id();
```

## Advanced

1. Used in Laravel.

Because the SDK is relatively simple, we don't provide an extension for Laravel. You can quickly integrate it into Laravel in the following way.

```php
// App\Providers\AppServiceProvider

use Godruoyi\Snowflake\Snowflake;
use Godruoyi\Snowflake\LaravelSequenceResolver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('snowflake', function ($app) {
            return (new Snowflake())
                ->setStartTimeStamp(strtotime('2019-10-10')*1000)
                ->setSequenceResolver(new LaravelSequenceResolver($app->get('cache.store')));
        });
    }
}
```

2. Custom

You can customize the sequence-number resolver by implementing the Godruoyi\Snowflake\SequenceResolver interface.

```php
class YourSequence implements SequenceResolver
{
    /**
     *  {@inheritdoc}
     */
    public function sequence(int $currentMillisecond)
    {
          // Just test.
        return mt_rand(0, 1);
    }
}

// usage

$snowflake->setSequenceResolver(new YourSequence);
$snowflake->id();
```

And you can use closure:

```php
$snowflake = new \Godruoyi\Snowflake\Snowflake;
$snowflake->setSequenceResolver(function ($currentMillisecond) {
    static $lastTime;
    static $sequence;

    if ($lastTime == $currentMillisecond) {
        ++$sequence;
    } else {
        $sequence = 0;
    }

    $lastTime = $currentMillisecond;

    return $sequence;
})->id();
```

## License

MIT
