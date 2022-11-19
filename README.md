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

use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerApplicationRunner;

ini_set('display_errors', 'stderr');

require_once __DIR__ . '/autoload.php';

(new RoadRunnerApplicationRunner(__DIR__, $_ENV['YII_DEBUG'], $_ENV['YII_ENV']))->run();
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

### Additional configuration

By default, the `RoadRunnerApplicationRunner` is configured to work with Yii application templates.
You can override the default configuration using immutable setters.

Override the name of the bootstrap configuration group as follows:

```php
/**
 * @var Yiisoft\Yii\Runner\RoadRunner\RoadRunnerApplicationRunner $runner
 */

// Bootstrap configuration group name by default is "bootstrap-web".
$runner = $runner->withBootstrap('my-bootstrap-config-group-name');

// Disables the use of bootstrap configuration group.
$runner = $runner->withoutBootstrap();
```

In debug mode, event configurations are checked, to override, use the following setters:

```php
/**
 * @var Yiisoft\Yii\Runner\RoadRunner\RoadRunnerApplicationRunner $runner
 */

// Configuration group name of events by default is "events-web".
$runner = $runner->withCheckingEvents('my-events-config-group-name');

// Disables checking of the event configuration group.
$runner = $runner->withoutCheckingEvents();
```

If the configuration instance settings differ from the default, such as configuration group names,
you can specify a customized configuration instance:

```php
/**
 * @var Yiisoft\Config\ConfigInterface $config
 * @var Yiisoft\Yii\Runner\RoadRunner\RoadRunnerApplicationRunner $runner
 */

$runner = $runner->withConfig($config);
```

The default container is `Yiisoft\Di\Container`. But you can specify any implementation
of the `Psr\Container\ContainerInterface`:

```php
/**
 * @var Psr\Container\ContainerInterface $container
 * @var Yiisoft\Yii\Runner\RoadRunner\RoadRunnerApplicationRunner $runner
 */

$runner = $runner->withContainer($container);
```

In addition to the error handler that is defined in the container, the runner uses a temporary error handler.
A temporary error handler is needed to handle the creation of configuration and container instances,
then the error handler configured in your application configuration will be used.

By default, the temporary error handler uses HTML renderer and logging to a file. You can override this as follows:

```php
/**
 * @var Psr\Log\LoggerInterface $logger
 * @var Yiisoft\ErrorHandler\Renderer\PlainTextRenderer $renderer
 * @var Yiisoft\Yii\Runner\RoadRunner\RoadRunnerApplicationRunner $runner
 */

$runner = $runner->withTemporaryErrorHandler(
    new Yiisoft\ErrorHandler\ErrorHandler($logger, $renderer),
);
```

You can also use your own implementation of the `Spiral\RoadRunner\Http\PSR7WorkerInterface`
(default is a `Spiral\RoadRunner\Http\PSR7Worker`):

```php
/**
 * @var Spiral\RoadRunner\Http\PSR7WorkerInterface $psr7Worker
 * @var Yiisoft\Yii\Runner\RoadRunner\RoadRunnerApplicationRunner $runner
 */

$runner = $runner->withPsr7Worker($psr7Worker);
```

## Temporal

Temporal is a distributed, scalable, durable, and highly available orchestration engine used to execute asynchronous long-running business logic in a scalable and resilient way.

Explore more about Temporal on the official website https://temporal.io and in the sdk repository: https://github.com/temporalio/sdk-php.

[//]: # (If you want to add support for Temporal you need to install the SDK and configure workflows and activities.)

### Installation

```shell
composer require temporal/sdk
```

### Configuration

Temporal has at least two main class types: Activity and Workflow.
Any activity must have the attribute `\Temporal\Activity\ActivityInterface`:

```php
namespace App\Activity;

#[\Temporal\Activity\ActivityInterface]
class MyActivity
{
   // ...
}
```

Any workflow must have the attribute `\Temporal\Workflow\WorkflowInterface`.

```php
namespace App\Workflow;

#[\Temporal\Workflow\WorkflowInterface]
class MyWorkflow
{
   // ...
}

```

To make the temporal engine see your activities and workflows you should configure the dependency injection container.
Add the tags `tag@temporal.activity` and `tag@temporal.workflow` to your services:

```php
// config/common/temporal.php

return [
    'tag@temporal.activity' => [
        \App\Activity\MyActivity::class,
    ],
    'tag@temporal.workflow' => [
        \App\Workflow\MyWorkflow::class,
    ],
]
```

> If you use not `yiisoft/di` as a container, make sure that 
> `$container->get('tag@temporal.activity')` and `$container->get('tag@temporal.workflow')`
> return all of your workflows and activities.

The last two things are to call `withEnabledTemporal(true)` on the `RoadRunnerApplicationRunner` and to add the following snippet to `config/params.php`:

```php
'yiisoft/yii-runner-roadrunner' => [
    'temporal' => [
        'enabled' => true,
    ]
]
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
