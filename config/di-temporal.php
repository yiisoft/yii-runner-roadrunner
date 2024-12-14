<?php

declare(strict_types=1);

use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Worker\Transport\Goridge;
use Temporal\Worker\Transport\HostConnectionInterface;
use Temporal\Worker\Transport\RoadRunner;
use Temporal\Worker\Transport\RPCConnectionInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory;
use Yiisoft\Yii\Runner\RoadRunner\Temporal\TemporalDeclarationProvider;

/**
 * @var $params array
 */

$temporalParams = $params['yiisoft/yii-runner-roadrunner']['temporal'];
if (!($temporalParams['enabled'] ?? false)) {
    return [];
}

return [
    DataConverterInterface::class => fn () => DataConverter::createDefault(),
    RPCConnectionInterface::class => fn () => Goridge::create(),
    WorkerFactoryInterface::class => WorkerFactory::class,
    WorkerFactory::class => fn () => WorkerFactory::create(),
    HostConnectionInterface::class => fn () => RoadRunner::create(),

    TemporalDeclarationProvider::class => fn () => new TemporalDeclarationProvider(
        $temporalParams['workflows'] ?? [],
        $temporalParams['activities'] ?? [],
    ),
];
