<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the application settings with values from the environment.
     *
     * Runs idempotently: existing keys are left untouched, missing keys are
     * created from the current env values.
     */
    public function run(): void
    {
        $defaults = [
            // Platform
            'platform_name' => config('app.name', 'Krettel'),

            // TeraBox integration (unofficial API keys / session cookie)
            'terabox_email' => config('terabox.email'),
            'terabox_password' => config('terabox.password'),
            'terabox_ndus' => config('terabox.ndus'),
            'terabox_remote_dir' => config('terabox.remote_dir', '/Apps/Krettel'),
            'terabox_web_host' => config('terabox.web_host', 'https://www.1024terabox.com'),
            'terabox_user_agent' => config('terabox.user_agent'),

            // Pixeldrain integration
            'pixeldrain_base_url' => config('pixeldrain.base_url', 'https://pixeldrain.net'),
            'pixeldrain_api_key' => config('pixeldrain.api_key'),
        ];

        foreach ($defaults as $key => $value) {
            if ($value !== null && $value !== '' && ! Setting::where('key', $key)->exists()) {
                Setting::create(['key' => $key, 'value' => $value]);
            }
        }
    }
}
