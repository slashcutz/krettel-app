<?php

namespace App\Support;

use App\Services\PixeldrainClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class PixeldrainImageStore
{
    /**
     * Upload an uploaded image to Pixeldrain and return a `pixeldrain://<id>` ref.
     *
     * jpg/png/webp are pushed (WebP is re-encoded to JPEG by the image proxy on
     * first view, so it still displays everywhere). Animated GIF/SVG stay on
     * local disk. Returns null on any failure so callers keep their local
     * fallback copy and the primary flow never breaks when Pixeldrain is
     * unreachable.
     */
    public static function upload(UploadedFile $file, string $filename): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            Log::channel('krettel')->info('[PIXELDRAIN-IMAGE] Skipped upload (format stays on local disk).', [
                'extension' => $ext,
                'filename' => $filename,
            ]);

            return null;
        }

        try {
            $pixeldrain = app(PixeldrainClient::class);

            if (! $pixeldrain->isConfigured()) {
                Log::channel('krettel')->warning('[PIXELDRAIN-IMAGE] Pixeldrain API key not configured; keeping local disk copy.', [
                    'filename' => $filename,
                ]);

                return null;
            }

            $id = $pixeldrain->upload($file->getRealPath(), $filename);

            Log::channel('krettel')->info('[PIXELDRAIN-IMAGE] Image uploaded.', [
                'file_id' => $id,
                'size_bytes' => $file->getSize(),
            ]);

            return 'pixeldrain://' . $id;
        } catch (\Throwable $e) {
            Log::channel('krettel')->error('[PIXELDRAIN-IMAGE] Upload failed; keeping local disk copy.', [
                'error' => $e->getMessage(),
                'filename' => $filename,
            ]);

            return null;
        }
    }
}
