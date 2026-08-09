<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare R2 Configuration
    |--------------------------------------------------------------------------
    |
    | R2 is used as a fast, CORS-enabled staging bucket so browsers upload
    | chunks straight to Cloudflare's edge (MB/s even from India) instead of
    | through the Wasmer server (which may be KB/s from some regions). The
    | background job then pulls the chunks and pushes them to the real
    | destination (Pixeldrain / TeraBox), and the R2 copy is deleted.
    |
    | Values can be overridden at runtime from the admin Settings page (keys
    | stored in the settings table: r2_account_id, r2_access_key_id,
    | r2_secret_access_key, r2_bucket, r2_endpoint, r2_enabled).
    |
    */

    'enabled' => env('R2_ENABLED', false),

    'account_id' => env('R2_ACCOUNT_ID', ''),

    // Cloudflare R2 S3 API tokens give their own access key id. If you used a
    // Cloudflare API token (cfat_...) instead, the access key id is the
    // account id — the presigner falls back to the account id when empty.
    'access_key_id' => env('R2_ACCESS_KEY_ID', ''),

    'secret_access_key' => env('R2_SECRET_ACCESS_KEY', ''),

    'bucket' => env('R2_BUCKET', ''),

    // Optional. Defaults to https://{account_id}.r2.cloudflarestorage.com
    'endpoint' => env('R2_ENDPOINT', ''),

    // Seconds a presigned PUT/GET/DELETE URL stays valid.
    'presign_expiry' => (int) env('R2_PRESIGN_EXPIRY', 3600),

];
