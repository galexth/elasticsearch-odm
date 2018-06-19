<?php

return [

    'default' => env('ES_CONNECTION', 'local'),

    'connections' => [

        'local' => [
            'callback' => null,
            'retryOnConflict' => 1,
            'log' => null,
            'connections' => [
                'host' => env('ELASTIC_HOST', '127.0.0.1'),
                'port' => env('ELASTIC_PORT', 9200),
            ],
        ]

    ]
];