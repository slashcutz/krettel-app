<?php

namespace App\Support;

use App\Services\TeraBoxClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class TeraBoxImageStore
{
    /**
     * Upload an uploaded image to TeraBox and return a `terabox://<path>` ref.
     *
     * Only raster formats (jpg/png) are pushed — they survive the proxy's GD
     * re-encode + static caching untouched. SVG/GIF/WebP stay on local disk.
     * Returns null on any failure so callers keep their local fallback copy
     * and the primary flow never breaks when TeraBox is unreachable.
     */
    public static function upload(UploadedFile $file, string $remoteDir, string $filename): ?string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if (! in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
            return null;
        }

        try {
            $terabox = app(TeraBoxClient::class);
            $terabox->ensureAuthenticated();
            $terabox->createDir($remoteDir);

            $remotePath = $terabox->uploadFile($file->getRealPath(), $remoteDir, $filename);

            Log::channel('krettel')->info('[TERABOX-IMAGE] Image uploaded.', [
                'remote_path' => $remotePath,
                'size_bytes' => $file->getSize(),
            ]);

            return 'terabox://' . $remotePath;
        } catch (\Throwable $e) {
            Log::channel('krettel')->error('[TERABOX-IMAGE] Upload failed; keeping local disk copy.', [
                'error' => $e->getMessage(),
                'remote_dir' => $remoteDir,
                'filename' => $filename,
            ]);

            return null;
        }
    }

    /**
     * Remote dir for an image subfolder under the configured TeraBox root.
     */
    public static function remoteDir(string $subdir): string
    {
        return rtrim(config('terabox.remote_dir', '/Apps/Krettel'), '/') . '/Images/' . trim($subdir, '/');
    }
}
