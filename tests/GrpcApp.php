<?php

declare(strict_types=1);

use Service\EchoInterface;
use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerGrpcApplicationRunner;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\Grpc\EchoService;

ini_set('display_errors', 'stderr');

require_once dirname(__DIR__) . '/vendor/autoload.php';

$application = new RoadRunnerGrpcApplicationRunner(
    rootPath: __DIR__,
    debug: true
);
$application
    ->setServices([
        EchoInterface::class => EchoService::class,
    ])
    ->run();
