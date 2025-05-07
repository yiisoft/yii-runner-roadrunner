<?php

declare(strict_types=1);

use Service\EchoInterface;
use Yiisoft\Yii\Runner\RoadRunner\Tests\Support\Grpc\EchoService;

return [
    EchoInterface::class => EchoService::class,
];
