<?php

namespace App\Support;

use App\Http\Controllers\TeraBoxImageController;

class TeraBoxImage
{
    /**
     * Resolve a stored image value to the fastest possible URL.
     *
     * - terabox:// / pixeldrain:// refs: serve the static /storage copy when
     *   warmed, otherwise fall back to the proxy route (which warms the static
     *   copy on first hit).
     * - http(s) URLs: used as-is.
     * - plain local paths: /storage/…
     */
    public static function url(?string $value, string $model, string|int|null $id): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        if (str_starts_with($value, 'terabox://') || str_starts_with($value, 'pixeldrain://')) {
            $local = TeraBoxImageController::localUrl($model, $id, $value);

            return $local ?: route('terabox.image', ['model' => $model, 'id' => $id]);
        }

        if (str_starts_with($value, 'http')) {
            return $value;
        }

        return asset('storage/' . $value);
    }
}
