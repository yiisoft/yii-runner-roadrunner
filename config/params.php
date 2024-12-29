<?php

declare(strict_types=1);

return [
    'yiisoft/yii-runner-roadrunner' => [
        'grpc' => [
            'services' => [],
        ],
        'temporal' => [
            'enabled' => false,
            'host' => 'localhost:7233',
            'workflows' => [],
            'activities' => [],
        ],
    ],
];
