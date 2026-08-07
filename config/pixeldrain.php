<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Pixeldrain Configuration
    |--------------------------------------------------------------------------
    |
    | Pixeldrain provides an official public API for file uploads, metadata
    | and downloads, authenticated with a personal API key.
    |
    | Use https://pixeldrain.net (or another official mirror) if your ISP
    | blocks the main .com domain.
    |
    */

    'base_url' => env('PIXELDRAIN_BASE_URL', 'https://pixeldrain.net'),

    'api_key' => env('PIXELDRAIN_API_KEY'),

    'email' => env('PIXELDRAIN_EMAIL'),

    'password' => env('PIXELDRAIN_PASSWORD'),

];
