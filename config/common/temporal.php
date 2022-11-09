<?php

declare(strict_types=1);

use Temporal\Worker\WorkerOptions;
use Temporal\WorkerFactory;

/**
 * @var $params array
 */

if (!($params['yiisoft/yii-runner-roadrunner']['temporal']['enabled'] ?? false)) {
    return [];
}

$options = $params['yiisoft/yii-runner-roadrunner']['temporal']['options'] ?? [];


return [
    'tag@temporal.workflow' => [],
    'tag@temporal.activity' => [],

    WorkerOptions::class => [
        'withMaxConcurrentActivityExecutionSize()' => $options['maxConcurrentActivityExecutionSize'],
        'withWorkerActivitiesPerSecond()' => $options['workerActivitiesPerSecond'],
        'withMaxConcurrentLocalActivityExecutionSize()' => $options['maxConcurrentLocalActivityExecutionSize'],
        'withWorkerLocalActivitiesPerSecond()' => $options['workerLocalActivitiesPerSecond'],
        'withTaskQueueActivitiesPerSecond()' => $options['taskQueueActivitiesPerSecond'],
        'withMaxConcurrentActivityTaskPollers()' => $options['maxConcurrentActivityTaskPollers'],
        'withMaxConcurrentWorkflowTaskExecutionSize()' => $options['maxConcurrentWorkflowTaskExecutionSize'],
        'withMaxConcurrentWorkflowTaskPollers()' => $options['maxConcurrentWorkflowTaskPollers'],
        'withStickyScheduleToStartTimeout()' => $options['stickyScheduleToStartTimeout'],
        'withWorkerStopTimeout()' => $options['workerStopTimeout'],
        'withEnableSessionWorker()' => $options['enableSessionWorker'],
        'withSessionResourceId()' => $options['sessionResourceId'],
        'withMaxConcurrentSessionExecutionSize()' => $options['maxConcurrentSessionExecutionSize'],
    ],
    WorkerFactory::class => [WorkerFactory::class, 'create'],
];
