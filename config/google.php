<?php

use Illuminate\Support\Facades\Facade;

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application. This value is used when the
    | framework needs to place the application's name in a notification or
    | any other location as required by the application or its packages.
    |
    */
    "auth"    => [
        'client_id'         => env('GOOGLE_CLIENT_ID', null),
        'client_secret'     => env('GOOGLE_CLIENT_SECRET', null),
        'redirect_url'      => env('GOOGLE_REDIRECT', null),
    ],

    "youtube"   => [
        'client_id'         => env('GOOGLE_CLIENT_ID', null),
        'client_secret'     => env('GOOGLE_CLIENT_SECRET', null),
        'redirect_url'      => env('GOOGLE_REDIRECT', null),
        'api_key'           => env('YOUTUBE_API_KEY', null),
    ],

    "drive"    => [
        'client_id'         => env('GOOGLE_SHEET_CLIENT_ID', null),
        'client_secret'     => env('GOOGLE_SHEET_CLIENT_SECRET', null),
    ],

    "sheet"    => [
        'id'                => env('GOOGLE_SHEET_ID', null),
        'api_key'           => env('GOOGLE_SHEET_API_KEY', null),
    ],

    "application" => [
        'url'               => env('APPLICATION_URL', null),
        'auth_redirect'     => env('GOOGLE_AUTH_REDIRECT', null),
    ]

];