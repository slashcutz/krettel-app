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

    // How video playback is served to the browser:
    //   redirect -> 307 to the Pixeldrain file URL (browser streams straight
    //               from Pixeldrain; zero Render egress bandwidth). Seek works
    //               because /api/file/{id} supports byte-range requests.
    //   proxy    -> stream through this server (fallback if Pixeldrain hotlink
    //               protection kicks in on the free tier).
    'stream_mode' => env('PIXELDRAIN_STREAM_MODE', 'redirect'),

    'password' => env('PIXELDRAIN_PASSWORD'),

];
