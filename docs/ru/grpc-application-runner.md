# Запуск gRPC приложения для RoadRunner

Yii RoadRunner Runner поддерживает запуск сервисов, использующих протокол [gRPC](https://grpc.io), через `RoadRunnerGrpcApplicationRunner`.

> Обратите внимание! Для поддержки PHP 8.2 используйте расширение `grpc` >= 1.56.0

## Основное использование

Для примера возьмем сервис `Pinger` описанный в [документации](https://roadrunner.dev/docs/plugins-grpc)

### Генерация DTO и сервисного интерфейса

Подробное описание как это сделать можно прочитать https://roadrunner.dev/docs/plugins-grpc

### Создание gRPC обработчика

Создайте файл обработчика в директории вашего приложения, например - `GrpcWorker.php`:

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

### Настройка конфигурации RoadRunner

Добавьте созданный файл обработчика в секцию `command` конфигурационного файла `.rr.yaml`

```yaml
version: '3'

server:
    command: "php GrpcWorker.php"
```

или в секцию `command` плагина `grpc` если вы поддерживаете несколько обработчиков (например `http` и `grpc`)

```yaml
version: '3'

grpc:
    pool:
        command: "php GrpcWorker.php"
```

