# gRPC application runner for RoadRunner

Yii RoadRunner Runner supports running [gRPC](https://grpc.io) services through `RoadRunnerGrpcApplicationRunner`.

> Notice! For PHP 8.2 support use the `grpc` extension >= 1.56.0

## General usage

For example, let's take the `Pinger` service described in the [documentation](https://roadrunner.dev/docs/plugins-grpc)

### DTO and service interface generation

A detailed description of how to do this can be read https://roadrunner.dev/docs/plugins-grpc

### Create worker for gRPC

Create a handler file in your application directory, for example - `GrpcWorker.php`:

```php
declare(strict_types=1);

use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerGrpcApplicationRunner;

ini_set('display_errors', 'stderr');

require_once dirname(__DIR__) . '/vendor/autoload.php';

$application = new RoadRunnerGrpcApplicationRunner(
    rootPath: __DIR__,
    debug: true
);
$application->setServices([
        PingerInterface::class => Pinger::class,
    ])
$application->run();
```

### Configure RoadRunner

Add the created handler file to the `command` section of the `.rr.yaml` config file

```yaml
version: '3'

server:
    command: "php GrpcWorker.php"
```

or to the `command` section of the `grpc` plugin if you support multiple handlers (e.g. `http` and `grpc`)

```yaml
version: '3'

grpc:
    pool:
        command: "php GrpcWorker.php"
```

