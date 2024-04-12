# Executor de aplicativo gRPC para RoadRunner

Yii RoadRunner Runner suporta a execução de serviços [gRPC](https://grpc.io) através de `RoadRunnerGrpcApplicationRunner`.

> Aviso! Para suporte ao PHP 8.2 use a extensão `grpc` >= 1.56.0

## Uso geral

Por exemplo, vamos pegar o serviço `Pinger` descrito na [documentação](https://roadrunner.dev/docs/plugins-grpc)

### DTO e geração de interface de serviço

Uma descrição detalhada de como fazer isso pode ser lida em <https://roadrunner.dev/docs/plugins-grpc>

### Criar trabalhador para gRPC

Crie um arquivo handler no diretório do seu aplicativo, por exemplo `GrpcWorker.php`:

```php
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

### Configurar RoadRunner

Adicione o arquivo handler criado na seção `command` do arquivo de configuração `.rr.yaml`

```yaml
version: '3'

server:
    command: "php GrpcWorker.php"
```

ou para a seção `command` do plugin `grpc` se você suporta múltiplos handlers (por exemplo, `http` e `grpc`)

```yaml
version: '3'

grpc:
    pool:
        command: "php GrpcWorker.php"
```

> Aviso! Se você também estiver implementando o lado do cliente (para chamar a API RoadRunner do aplicativo PHP), você precisará adicionar manualmente o pacote `grpc/grpc` da versão correta ao seu compositor.json.
