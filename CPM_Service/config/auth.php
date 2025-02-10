<?php

return [
    'defaults' => [
        'guard' => 'investor',
        'passwords' => 'users',
    ],

    'guards' => [
        'investor' => [
            'driver' => 'jwt',
            'provider' => 'users',
        ],
        
        'admin' => [
            'driver' => 'jwt',
            'provider' => 'admin'
        ]
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => \App\Models\Auth\Investor::class
        ],
        'admin' => [
            'driver' => 'eloquent',
            'model' => \App\Models\Auth\User::class
        ]
    ]
];