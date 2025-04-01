<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://yiisoft.github.io/docs/images/yii_logo.svg" height="100px" alt="Yii">
    </a>
    <h1 align="center">Yii RoadRunner Runner</h1>
    <br>
</p>

[![Latest Stable Version](https://poser.pugx.org/yiisoft/yii-runner-roadrunner/v)](https://packagist.org/packages/yiisoft/yii-runner-roadrunner)
[![Total Downloads](https://poser.pugx.org/yiisoft/yii-runner-roadrunner/downloads)](https://packagist.org/packages/yiisoft/yii-runner-roadrunner)
[![Build status](https://github.com/yiisoft/yii-runner-roadrunner/actions/workflows/build.yml/badge.svg)](https://github.com/yiisoft/yii-runner-roadrunner/actions/workflows/build.yml)
[![Code Coverage](https://codecov.io/gh/yiisoft/yii-runner-roadrunner/branch/master/graph/badge.svg)](https://codecov.io/gh/yiisoft/yii-runner-roadrunner)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Fyii-runner-roadrunner%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/yii-runner-roadrunner/master)
[![static analysis](https://github.com/yiisoft/yii-runner-roadrunner/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/yii-runner-roadrunner/actions?query=workflow%3A%22static+analysis%22)
[![type-coverage](https://shepherd.dev/github/yiisoft/yii-runner-roadrunner/coverage.svg)](https://shepherd.dev/github/yiisoft/yii-runner-roadrunner)

The package contains a bootstrap for running Yii3 applications using [RoadRunner](https://roadrunner.dev/).

## Requirements

- PHP 8.1 or higher.

## Installation

The package could be installed with [Composer](https://getcomposer.org):

```shell
composer require yiisoft/yii-runner-roadrunner
```

## General usage

Get RoadRunner:

```shell
./vendor/bin/rr get
```

Create `worker.php` in your application root directory:

```php
use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerHttpApplicationRunner;

ini_set('display_errors', 'stderr');

require_once __DIR__ . '/autoload.php';

(new RoadRunnerHttpApplicationRunner(
    rootPath: __DIR__, 
    debug: $_ENV['YII_DEBUG'], 
    checkEvents: $_ENV['YII_DEBUG'], 
    environment: $_ENV['YII_ENV']
))->run();
```

Specify it in your `.rr.yaml`:

```yaml
version: '3'
server:
    command: "php ./worker.php"

rpc:
    listen: tcp://127.0.0.1:6001

http:
    address: :8082
    pool:
        num_workers: 8
        # Debug mode for the pool. In this mode, pool will not pre-allocate the worker.
        # Worker (only 1, num_workers ignored) will be allocated right after the request arrived.
        debug: false
    middleware: ["static", "headers"]
    static:
        dir:   "./public"
        forbid: [".php", ".htaccess"]
    headers:
        response:
            "Cache-Control": "no-cache"

logs:
    mode: production
    level: warn
```

> **Note**:
> Official [configuration reference](https://roadrunner.dev/docs/intro-config/). You can also activate `RoadRunner`
> schema in your IDE to get autocompletion hints.

Run RoadRunner with the config specified:

```shell
./rr serve
```

### Additional configuration

By default, the `RoadRunnerHttpApplicationRunner` is configured to work with Yii application templates and follows the
[config groups convention](https://github.com/yiisoft/docs/blob/master/022-config-groups.md).

You can override the default configuration using constructor parameters and immutable setters.

#### Constructor parameters

`$rootPath` — the absolute path to the project root.

`$debug` — whether the debug mode is enabled.

`$checkEvents` — whether check events' configuration.

`$environment` — the environment name.

`$bootstrapGroup` — the bootstrap configuration group name.

`$eventsGroup` — the events' configuration group name.

`$diGroup` — the container definitions' configuration group name.

`$diProvidersGroup` — the container providers' configuration group name.

`$diDelegatesGroup` — the container delegates' configuration group name.

`$diTagsGroup` — the container tags' configuration group name.

`$paramsGroup` — the config parameters group name.

`$nestedParamsGroups` — configuration group names that are included into config parameters group. This is needed for
recursive merge parameters.

`$nestedEventsGroups` — configuration group names that are included into events' configuration group. This is needed for
reverse and recursive merge events' configurations.

#### Immutable setters

If the configuration instance settings differ from the default you can specify a customized configuration instance:

```php
/**
 * @var Yiisoft\Config\ConfigInterface $config
 * @var Yiisoft\Yii\Runner\RoadRunner\RoadRunnerHttpApplicationRunner $runner
 */

$runner = $runner->withConfig($config);
```

The default container is `Yiisoft\Di\Container`. But you can specify any implementation
of the `Psr\Container\ContainerInterface`:

```php
/**
 * @var Psr\Container\ContainerInterface $container
 * @var Yiisoft\Yii\Runner\RoadRunner\RoadRunnerHttpApplicationRunner $runner
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
 * @var Yiisoft\Yii\Runner\RoadRunner\RoadRunnerHttpApplicationRunner $runner
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
 * @var Yiisoft\Yii\Runner\RoadRunner\RoadRunnerHttpApplicationRunner $runner
 */

$runner = $runner->withPsr7Worker($psr7Worker);
```

## Temporal

Temporal is a distributed, scalable, durable, and highly available orchestration engine used to execute asynchronous long-running business logic in a scalable and resilient way.

Explore more about Temporal on [the official website](https://temporal.io) and in [the SDK repository](https://github.com/temporalio/sdk-php).

> If you want to add support for Temporal you need to install the SDK and configure workflows and activities as described below.

### Installation

```shell
composer require temporal/sdk
```

### Configuration

Temporal has at least two main class types: [Activity](https://docs.temporal.io/activities) and [Workflow](https://docs.temporal.io/workflows).
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

Configure Temporal engine to make it able to work with your activities and workflows in the `params.php`:

```php
// config/common/params.php

return [
    'yiisoft/yii-runner-roadrunner' => [
        'temporal' => [
            'enabled' => true,
            'host' => 'localhost:7233', // host of Temporal engine
            'workflows' => [
                \App\Workflow\MyWorkflow::class,
            ],
            'activities' => [
                \App\Activity\MyActivity::class,
            ],
        ],
    ],
]
```

> If you use another container instead of `yiisoft/di`, make sure that 
> `\Yiisoft\Yii\Runner\RoadRunner\Temporal\TemporalDeclarationProvider` 
> is registered and returns all of your workflows and activities.

The last thing is to call `withTemporalEnabled(true)` on the `\Yiisoft\Yii\Runner\RoadRunner\RoadRunnerHttpApplicationRunner` in the `public/index.php`:

```php
(new RoadRunnerHttpApplicationRunner())
    ->withTemporalEnabled(true)
    ->run();
```

## Testing

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:
## Documentation

- Guide: [English](docs/guide/en/README.md), [Português - Brasil](docs/guide/pt-BR/README.md), [Русский](docs/guide/ru/README.md)
- [Internals](docs/internals.md)

If you need help or have a question, the [Yii Forum](https://forum.yiiframework.com/c/yii-3-0/63) is a good place for
that. You may also check out other [Yii Community Resources](https://www.yiiframework.com/community).

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
