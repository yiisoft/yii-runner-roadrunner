<?php

declare(strict_types=1);

use Service\EchoInterface;
use Yiisoft\Yii\Runner\RoadRunner\RoadRunnerGrpcApplicationRunner;

ini_set('display_errors', 'stderr');

require_once dirname(__DIR__) . '/vendor/autoload.php';

$application = new RoadRunnerGrpcApplicationRunner(
    rootPath: __DIR__ . '/Support',
    debug: true
);

$application->setServices([EchoInterface::class])->run();
