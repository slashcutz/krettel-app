<?php

namespace App\Support;

use Illuminate\Support\Str;

class DeviceContext
{
    public const COOKIE = 'krettel_device';

    public static function id(): string
    {
        $cookie = request()->cookie(self::COOKIE);

        return is_string($cookie) && $cookie !== '' ? $cookie : Str::uuid()->toString();
    }

    public static function type(): string
    {
        $ua = request()->userAgent() ?? '';

        if (preg_match('/smart-tv|smarttv|appletv|roku|firetv|chromecast|android.*tv/i', $ua)) {
            return 'tv';
        }

        if (preg_match('/(tablet|ipad|playbook|silk)|(android(?!.*mobi))/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/Mobile|iP(hone|od)|Android|BlackBerry|IEMobile|Kindle|Silk-Accelerated|(hpw|web)OS|Opera M(obi|ini)/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    public static function ip(): string
    {
        return request()->ip() ?? '';
    }

    /**
     * The ownership scope for favorites / watch history.
     *
     * Logged-in users are keyed by user_id; guests are keyed by an anonymous
     * device cookie so each device (Android TV, mobile, tablet, desktop) keeps
     * its own list.
     */
    public static function scope(): array
    {
        $userId = auth()->check() ? auth()->id() : null;

        return [
            'user_id' => $userId,
            'device_id' => $userId ? null : self::id(),
            'device_type' => self::type(),
            'ip_address' => self::ip(),
        ];
    }

    /**
     * Query scope helper that matches either the user or the guest device.
     */
    public static function contextQuery($query): void
    {
        $scope = self::scope();

        $query->where(function ($q) use ($scope) {
            $q->where('user_id', $scope['user_id'])
                ->orWhere(function ($q2) use ($scope) {
                    $q2->whereNull('user_id')->where('device_id', $scope['device_id']);
                });
        });
    }
}
