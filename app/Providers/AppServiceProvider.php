<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            $request = request();
            
            // Log the activity
            \App\Models\ActivityLog::create([
                'user_id' => $event->user->id,
                'description' => 'User logged in',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Update or create device session
            $userAgent = $request->userAgent();
            $deviceType = 'Desktop';
            if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $userAgent)) {
                $deviceType = 'Tablet';
            } elseif (preg_match('/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/', $userAgent)) {
                $deviceType = 'Mobile';
            } elseif (preg_match('/smart-tv|smarttv|appletv|roku|firetv|chromecast/i', $userAgent)) {
                $deviceType = 'Smart TV';
            }

            // Simple browser detection
            $browser = 'Unknown';
            if (strpos($userAgent, 'Firefox') !== false) $browser = 'Firefox';
            elseif (strpos($userAgent, 'Chrome') !== false) $browser = 'Chrome';
            elseif (strpos($userAgent, 'Safari') !== false) $browser = 'Safari';
            elseif (strpos($userAgent, 'Edge') !== false) $browser = 'Edge';

            // We generate a simple device_id based on IP + UserAgent for demo purposes
            $deviceId = md5($request->ip() . $userAgent);

            \App\Models\DeviceSession::updateOrCreate(
                ['user_id' => $event->user->id, 'device_id' => $deviceId],
                [
                    'device_type' => $deviceType,
                    'browser' => $browser,
                    'ip_address' => $request->ip(),
                    'last_active_at' => now(),
                ]
            );
        });
    }
}
