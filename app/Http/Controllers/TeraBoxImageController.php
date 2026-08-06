<?php

namespace App\Http\Controllers;

use App\Models\Collection;
use App\Models\Video;
use App\Services\TeraBoxClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TeraBoxImageController extends Controller
{
    /**
     * Stream an image stored on TeraBox.
     *
     * The stored value uses the scheme `terabox://<remote-path>` because TeraBox
     * direct links expire (~8h). This endpoint resolves a fresh link (cached 7h)
     * and proxies the bytes. The bytes themselves are cached locally (file store,
     * 30 days) and downscaled once via GD so card images load instantly instead
     * of round-tripping TeraBox on every page view.
     */
    public function show(string $model, int $id)
    {
        $resource = $model === 'collection' ? Collection::find($id) : Video::find($id);
        abort_unless($resource, 404);

        $value = $model === 'collection'
            ? ($resource->terabox_image ?: $resource->image)
            : ($resource->terabox_image ?: $resource->poster ?: $resource->thumbnail);

        abort_unless(is_string($value) && str_starts_with($value, 'terabox://'), 404);

        $remotePath = substr($value, 9);

        try {
            $payload = static::load($model, $id, $remotePath);
        } catch (\Throwable $e) {
            abort(500, 'Could not fetch image from TeraBox.');
        }

        return response($payload['body'], $payload['status'])
            ->header('Content-Type', $payload['type'])
            ->header('Cache-Control', 'public, max-age=604800')
            ->header('ETag', '"' . md5($payload['body']) . '"')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    /**
     * Load (and cache) the optimized image bytes for a terabox reference.
     *
     * Cache keys are derived from the remote path so changing the stored image
     * automatically busts every layer without manual cache clearing.
     */
    public static function load(string $model, int $id, string $remotePath): array
    {
        $key = 'terabox_img_bytes_' . md5($remotePath);

        $payload = Cache::store('file')->remember($key, now()->addDays(30), function () use ($remotePath) {
            $linkKey = 'terabox_img_link_' . md5($remotePath);
            $url = Cache::remember($linkKey, now()->addHours(7), function () use ($remotePath) {
                $terabox = app(TeraBoxClient::class);
                $terabox->ensureAuthenticated();

                return $terabox->getDirectLink($remotePath);
            });

            $result = app(TeraBoxClient::class)->fetchSegment($url);

            if (! isset($result['body']) || $result['body'] === '') {
                throw new \RuntimeException('Empty image payload from TeraBox.');
            }

            [$body, $type] = static::optimizeImage($result['body'], $result['type'] ?? 'image/jpeg');

            return ['body' => $body, 'type' => $type, 'status' => 200];
        });

        // Keep a static copy in public storage so cards can skip PHP entirely.
        static::ensureLocal($model, $id, $remotePath, $payload['body'], $payload['type']);

        return $payload;
    }

    /**
     * The absolute local path (in public storage) for an image reference.
     *
     * Files are keyed by the remote path only (not model/id), so shared preview
     * images deduplicate and every reference resolves to the same static file.
     */
    protected static function localFile(string $remotePath, string $ext): string
    {
        $hash = substr(md5($remotePath), 0, 10);

        return storage_path('app/public/terabox/' . $hash . '.' . $ext);
    }

    /**
     * Write the optimized bytes as a static file served via /storage (no PHP).
     */
    protected static function ensureLocal(string $model, int $id, string $remotePath, string $body, string $type): void
    {
        $ext = str_contains($type, 'png') ? 'png' : 'jpg';
        $file = static::localFile($remotePath, $ext);

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
    public static function localUrl(string $model, int $id, ?string $value): ?string
    {
        if (! is_string($value) || ! str_starts_with($value, 'terabox://')) {
            return null;
        }

        $remotePath = substr($value, 9);
        $hash = substr(md5($remotePath), 0, 10);
        $dir = storage_path('app/public/terabox');

        foreach (['jpg', 'png'] as $ext) {
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
    public static function warm(string $model, int $id, string $remotePath, bool $force = false): void
    {
        if ($force) {
            Cache::store('file')->forget('terabox_img_bytes_' . md5($remotePath));
            Cache::forget('terabox_img_link_' . md5($remotePath));
        }

        static::load($model, $id, $remotePath);
    }
}
