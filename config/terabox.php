<?php

return [

    /*
    |--------------------------------------------------------------------------
    | TeraBox Configuration
    |--------------------------------------------------------------------------
    |
    | TeraBox does not provide an official developer API. This integration uses
    | the internal web API that the TeraBox front-end uses, authenticated with
    | the session token ("ndus") obtained after login. This is unofficial and
    | may break if TeraBox changes their endpoints.
    |
    | You can authenticate in two ways:
    |   1. TERABOX_EMAIL + TERABOX_PASSWORD — the client will log in at runtime
    |      and obtain the session token automatically (may hit a captcha).
    |   2. TERABOX_NDUS — paste the "ndus" cookie from a logged-in browser
    |      (DevTools -> Application -> Cookies -> www.terabox.com). This is the
    |      most reliable method.
    |
    */

    'email' => env('TERABOX_EMAIL'),
    'password' => env('TERABOX_PASSWORD'),
    'ndus' => env('TERABOX_NDUS'),

    'remote_dir' => env('TERABOX_REMOTE_DIR', '/Apps/Krettel'),

    'web_host' => env('TERABOX_WEB_HOST', 'https://www.1024terabox.com'),

    'user_agent' => env(
        'TERABOX_USER_AGENT',
        'terabox;1.40.0.132;PC;PC-Windows;10.0.26100;WindowsTeraBox'
    ),

];
