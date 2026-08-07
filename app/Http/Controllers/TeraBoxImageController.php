<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Video;
use App\Services\PixeldrainClient;
use App\Services\TeraBoxClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TeraBoxImageController extends Controller
{
    /**
     * Stream an image stored on a remote provider.
     *
     * Stored values use a scheme-prefixed ref: `terabox://<remote-path>` or
     * `pixeldrain://<file-id>`. TeraBox direct links expire (~8h), so this
     * endpoint resolves a fresh link (cached 7h) and proxies the bytes. The
     * bytes themselves are cached locally (file store, 30 days) and downscaled
     * once via GD so card images load instantly instead of round-tripping the
     * remote host on every page view.
     */
    public function show(string $model, string $id)
    {
        $value = match ($model) {
            'collection' => ($resource = Collection::find((int) $id))
                ? ($resource->terabox_image ?: $resource->image)
                : null,
            'video' => ($resource = Video::find((int) $id))
                ? ($resource->terabox_image ?: $resource->poster ?: $resource->thumbnail)
                : null,
            'banner' => ($resource = \App\Models\Banner::find((int) $id))
                ? $resource->image_url
                : null,
            'settings' => \App\Models\Setting::get($id),
            default => null,
        };

        [$scheme, $remoteKey] = static::parseRef($value);

        abort_unless($scheme !== null, 404);

        try {
            $payload = static::load($model, $id, $scheme, $remoteKey);
        } catch (\Throwable $e) {
            abort(500, 'Could not fetch image from remote storage.');
        }

        return response($payload['body'], $payload['status'])
            ->header('Content-Type', $payload['type'])
            ->header('Cache-Control', 'public, max-age=604800')
            ->header('ETag', '"' . md5($payload['body']) . '"')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Split a stored image ref into [scheme, remote key].
     *
     * @return array{0: string|null, 1: string|null}
     */
    public static function parseRef(mixed $value): array
    {
        if (is_string($value) && str_starts_with($value, 'terabox://')) {
            return ['terabox', substr($value, 9)];
        }

        if (is_string($value) && str_starts_with($value, 'pixeldrain://')) {
            return ['pixeldrain', substr($value, 12)];
        }

        return [null, null];
    }

    /**
     * Load (and cache) the optimized image bytes for a remote image reference.
     *
     * Cache keys are derived from the scheme + remote key so changing the
     * stored image automatically busts every layer without manual clearing.
     */
    public static function load(string $model, string|int $id, string $scheme, string $remoteKey): array
    {
        $key = 'remote_img_bytes_' . md5($scheme . '://' . $remoteKey);

        $payload = Cache::store('file')->remember($key, now()->addDays(30), function () use ($scheme, $remoteKey) {
            if ($scheme === 'pixeldrain') {
                $result = app(PixeldrainClient::class)->downloadBytes($remoteKey);
                $body = $result['body'];
                $type = $result['type'] ?? 'image/jpeg';
            } else {
                $linkKey = 'terabox_img_link_' . md5($remoteKey);
                $url = Cache::remember($linkKey, now()->addHours(7), function () use ($remoteKey) {
                    $terabox = app(TeraBoxClient::class);
                    $terabox->ensureAuthenticated();

                    return $terabox->getDirectLink($remoteKey);
                });

                $result = app(TeraBoxClient::class)->fetchSegment($url);

                if (! isset($result['body']) || $result['body'] === '') {
                    throw new \RuntimeException('Empty image payload from TeraBox.');
                }

                $body = $result['body'];
                $type = $result['type'] ?? 'image/jpeg';
            }

            [$body, $type] = static::optimizeImage($body, $type);

            return ['body' => $body, 'type' => $type, 'status' => 200];
        });

        // Keep a static copy in public storage so cards can skip PHP entirely.
        static::ensureLocal($model, $id, $scheme, $remoteKey, $payload['body'], $payload['type']);

        return $payload;
    }

    /**
     * The absolute local path (in public storage) for an image reference.
     *
     * Files are keyed by the scheme + remote key only (not model/id), so shared
     * preview images deduplicate and every reference resolves to one static file.
     */
    protected static function localFile(string $scheme, string $remoteKey, string $ext): string
    {
        $hash = substr(md5($scheme . '://' . $remoteKey), 0, 10);

        return storage_path('app/public/terabox/' . $hash . '.' . $ext);
    }

    /**
     * Write the optimized bytes as a static file served via /storage (no PHP).
     */
    protected static function ensureLocal(string $model, string|int $id, string $scheme, string $remoteKey, string $body, string $type): void
    {
        $ext = str_contains($type, 'png') ? 'png' : (str_contains($type, 'webp') ? 'webp' : 'jpg');
        $file = static::localFile($scheme, $remoteKey, $ext);

        if (! file_exists($file)) {
            $dir = dirname($file);
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($file, $body);
        }
    }

    /**
     * Public URL of the static copy, or null when not yet warmed.
     */
    public static function localUrl(string $model, string|int $id, ?string $value): ?string
    {
        [$scheme, $remoteKey] = static::parseRef($value);

        if ($scheme === null || $remoteKey === null) {
            return null;
        }

        $hash = substr(md5($scheme . '://' . $remoteKey), 0, 10);
        $dir = storage_path('app/public/terabox');

        foreach (['jpg', 'png', 'webp'] as $ext) {
            if (file_exists($dir . '/' . $hash . '.' . $ext)) {
                return asset('storage/terabox/' . $hash . '.' . $ext);
            }
        }

        return null;
    }

    /**
     * Downscale + re-encode an image once so cards stay lightweight.
     */
    protected static function optimizeImage(string $bytes, string $mime): array
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            return [$bytes, $mime];
        }

        $img = @imagecreatefromstring($bytes);
        if ($img === false) {
            return [$bytes, $mime];
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $maxW = 900;

        if ($w > $maxW) {
            $nh = (int) round($h * $maxW / $w);
            $resized = imagecreatetruecolor($maxW, $nh);
            imagecopyresampled($resized, $img, 0, 0, 0, 0, $maxW, $nh, $w, $h);
            imagedestroy($img);
            $img = $resized;
        }

        $isPng = str_contains($mime, 'png');
        ob_start();
        if ($isPng) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            imagepng($img, null, 8);
        } else {
            imagejpeg($img, null, 82);
        }
        $out = ob_get_clean();
        imagedestroy($img);

        if ($out === false || $out === '') {
            return [$bytes, $mime];
        }

        return [$out, $isPng ? 'image/png' : 'image/jpeg'];
    }

    /**
     * Pre-fetch + cache an image reference (used by `images:warm`).
     */
    public static function warm(string $model, string|int $id, string $value, bool $force = false): void
    {
        [$scheme, $remoteKey] = static::parseRef($value);

        if ($scheme === null || $remoteKey === null) {
            return;
        }

        if ($force) {
            Cache::store('file')->forget('remote_img_bytes_' . md5($scheme . '://' . $remoteKey));
            Cache::forget('terabox_img_link_' . md5($remoteKey));
        }

        static::load($model, $id, $scheme, $remoteKey);
    }
}
