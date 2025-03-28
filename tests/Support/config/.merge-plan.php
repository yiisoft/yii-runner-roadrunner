<?php

declare(strict_types=1);

// Do not edit. Content will be replaced.
return [
    '/' => [
        'di-web' => [
            '/' => [
                'web/di/*.php',
            ],
        ],
        'params' => [
            '/' => [
                'params.php',
            ],
        ],
        'events-web' => [
            '/' => [
                '$events',
            ],
        ],
        'params-web' => [
            '/' => [
                '$params',
            ],
        ],
        'params-grpc' => [
            '/' => [
                '$params',
            ],
        ],
        'di-grpc' => [
            '/' => [
                'grpc/di/*.php',
            ],
        ],
        'di-delegates' => [
            '/' => [
                'di-delegates.php'
            ],
        ],
        'di-delegates-grpc' => [
            '/' => [
                '$di-delegates',
            ],
        ],
        'di-delegates-web' => [
            '/' => [
                '$di-delegates',
            ],
        ],
        'di-providers' => [
            '/' => [
                'di-providers.php'
            ],
        ],
        'di-providers-grpc' => [
            '/' => [
                '$di-providers',
            ],
        ],
        'di-providers-web' => [
            '/' => [
                '$di-providers',
            ],
        ],
        'events' => [
            '/' => [],
        ],
        'events-grpc' => [
            '/' => [
                '$events',
            ],
        ],
        'events-fail' => [
            '/' => [
                'events-fail.php',
            ],
        ],
        'bootstrap' => [
            '/' => [],
        ],
        'bootstrap-web' => [
            '/' => [
                '$bootstrap',
                'web/bootstrap.php',
            ],
        ],
        'bootstrap-grpc' => [
            '/' => [
                '$bootstrap',
                'grpc/bootstrap.php',
            ],
        ],
    ],
];
