<?php

declare(strict_types=1);

use Service\EchoInterface;
use Yiisoft\Log\Logger;
use Yiisoft\Log\Target\File\FileTarget;
use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerGrpcApplicationRunner;

ini_set('display_errors', 'stderr');

require_once dirname(__DIR__) . '/vendor/autoload.php';

$application = new RoadRunnerGrpcApplicationRunner(
    rootPath: __DIR__ . '/Support',
    debug: true,
    bootstrapGroup: 'bootstrap-grpc',
    logger: new Logger([new FileTarget(__DIR__ . '/Support/runtime/logs/app.log')])
);

$application->setServices([EchoInterface::class])->run();
