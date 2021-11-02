<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px">
    </a>
    <h1 align="center">Yii RoadRunner Runner</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/yii-runner-roadrunner/v/stable.png)](https://packagist.org/packages/yiisoft/yii-runner-roadrunner)
[![Total Downloads](https://poser.pugx.org/yiisoft/yii-runner-roadrunner/downloads.png)](https://packagist.org/packages/yiisoft/yii-runner-roadrunner)
[![Build status](https://github.com/yiisoft/yii-runner-roadrunner/workflows/build/badge.svg)](https://github.com/yiisoft/yii-runner-roadrunner/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/yii-runner-roadrunner/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/yii-runner-roadrunner/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/yii-runner-roadrunner/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/yii-runner-roadrunner/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fyii-runner-roadrunner%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/yii-runner-roadrunner/master)
[![static analysis](https://github.com/yiisoft/yii-runner-roadrunner/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/yii-runner-roadrunner/actions?query=workflow%3A%22static+analysis%22)
[![type-coverage](https://shepherd.dev/github/yiisoft/yii-runner-roadrunner/coverage.svg)](https://shepherd.dev/github/yiisoft/yii-runner-roadrunner)

The package contains a bootstrap for running Yii3 applications using [RoadRunner](https://roadrunner.dev/).

## Requirements

- PHP 8.0 or higher.

## Installation

The package could be installed with composer:

```shell
composer require yiisoft/yii-runner-roadrunner --prefer-dist
```

## General usage

Get RoadRunner:

```
./vendor/bin/rr get
```

Create `worker.php` in your application root directory:

```php
<?php

declare(strict_types=1);

use App\Runner\RoadRunnerApplicationRunner;

ini_set('display_errors', 'stderr');

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/preload.php';

(new RoadRunnerApplicationRunner($_ENV['YII_DEBUG'], $_ENV['YII_ENV']))->run();
```

Specify it in your `.rr.yaml`:

```yaml
server:
    command: "php /worker.php"

rpc:
    listen: tcp://127.0.0.1:6001

http:
    address: :8082
    pool:
        num_workers: 4
        max_jobs: 64
    middleware: ["static", "headers"]
    static:
        dir:   "/public"
        forbid: [".php", ".htaccess"]
    headers:
        response:
            "Cache-Control": "no-cache"

reload:
    interval: 1s
    patterns: [ ".php" ]
    services:
        http:
            recursive: true
            dirs: [ "/" ]

logs:
    mode: production
    level: warn
```

Run RoadRunner with the config specified:

```
./vendor/bin/rr serve -c ./.rr.yaml
```

## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework with
[Infection Static Analysis Plugin](https://github.com/Roave/infection-static-analysis-plugin). To run it:

```shell
./vendor/bin/roave-infection-static-analysis-plugin
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

## License

The Yii RoadRunner Runner is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).

## Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

## Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)
