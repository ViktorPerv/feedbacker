<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin account
    |--------------------------------------------------------------------------
    |
    | Credentials for the project admin, created by AdminUserSeeder when you
    | run `make seed`. Override them in laravel/.env for anything other than
    | local development — never ship the defaults.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Feedbacker Admin'),
        'email' => env('ADMIN_EMAIL', 'admin@feedbacker.test'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
