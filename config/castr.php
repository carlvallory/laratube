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
        
    'api_key'       => env('CASTR_API_KEY', null),
    'stream_id'     => env('CASTR_STREAM_ID', null),
    'stream_url'    => env('CASTR_STREAM_URL', null),
    'stream_name'   => env('CASTR_STREAM_NAME', null),

];