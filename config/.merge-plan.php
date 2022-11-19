<?php

declare(strict_types=1);

// Do not edit. Content will be replaced.
return [
    '/' => [
        'web' => [
            'yiisoft/error-handler' => [
                'config/web.php',
            ],
            'yiisoft/middleware-dispatcher' => [
                'config/web.php',
            ],
            'yiisoft/yii-event' => [
                'config/web.php',
            ],
        ],
        'events-console' => [
            'yiisoft/log' => [
                'config/events-console.php',
            ],
            'yiisoft/yii-event' => [
                '$events',
                'config/events-console.php',
            ],
        ],
        'events-web' => [
            'yiisoft/log' => [
                'config/events-web.php',
            ],
            'yiisoft/yii-event' => [
                '$events',
                'config/events-web.php',
            ],
        ],
        'common' => [
            'yiisoft/log-target-file' => [
                'config/common.php',
            ],
            'yiisoft/yii-event' => [
                'config/common.php',
            ],
            '/' => [
                'common/*.php',
            ],
        ],
        'params' => [
            'yiisoft/log-target-file' => [
                'config/params.php',
            ],
            '/' => [
                'params.php',
            ],
        ],
        'console' => [
            'yiisoft/yii-event' => [
                'config/console.php',
            ],
        ],
        'events' => [
            'yiisoft/yii-event' => [
                'config/events.php',
            ],
        ],
    ],
];
