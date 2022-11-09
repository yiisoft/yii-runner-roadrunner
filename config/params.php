<?php

declare(strict_types=1);

return [
    'yiisoft/yii-runner-roadrunner' => [
        'temporal' => [
            'options' => [
                'maxConcurrentActivityExecutionSize' => 0,
                'workerActivitiesPerSecond' => 0,
                'maxConcurrentLocalActivityExecutionSize' => 0,
                'workerLocalActivitiesPerSecond' => 0,
                'taskQueueActivitiesPerSecond' => 0,
                'maxConcurrentActivityTaskPollers' => 0,
                'maxConcurrentWorkflowTaskExecutionSize' => 0,
                'maxConcurrentWorkflowTaskPollers' => 0,
                'stickyScheduleToStartTimeout' => null,
                'workerStopTimeout' => null,
                'enableSessionWorker' => false,
                'sessionResourceId' => null,
                'maxConcurrentSessionExecutionSize' => 1000,
            ],
        ],
    ],
];
