<?php

return [

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            /*
            | Optional browser WebSocket overrides (REVERB_CLIENT_*).
            | Leave unset — realtime.js uses window.location.hostname on port 443/80.
            | REVERB_HOST / REVERB_PORT are for the Reverb server process, not the browser.
            */
            'client' => [
                'host' => env('REVERB_CLIENT_HOST'),
                'port' => env('REVERB_CLIENT_PORT'),
                'scheme' => env('REVERB_CLIENT_SCHEME'),
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
