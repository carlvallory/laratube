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

    'client_id'         => env('GOOGLE_CLIENT_ID', null),
    'client_secret'     => env('GOOGLE_CLIENT_SECRET', null),
    'redirect_url'      => env('GOOGLE_REDIRECT', null),

];