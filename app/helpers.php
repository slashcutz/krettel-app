<?php

if (!function_exists('format_duration')) {
    function format_duration($seconds): string
    {
        $seconds = (int) $seconds;

        if ($seconds <= 0) {
            return 'Movie';
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        if ($h > 0) {
            return $s > 0 ? sprintf('%dh %dm %ds', $h, $m, $s) : sprintf('%dh %dm', $h, $m);
        }

        return $s > 0 ? sprintf('%dm %ds', $m, $s) : sprintf('%dm', $m);
    }
}

if (!function_exists('media_temp_dir')) {
    /**
     * Scratch directory for large ffmpeg temp outputs (audio splits, quality
     * variants, subtitle conversions). Lives on the app's storage volume —
     * NOT the OS /tmp, which some hosting platforms cap very small (e.g. 2GB)
     * and crash the instance when transcoding multi-GB files. Stale files
     * older than a day are swept opportunistically.
     */
    function media_temp_dir(): string
    {
        $dir = storage_path('app/media-tmp');

        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        try {
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*') as $entry) {
                if (is_file($entry) && (time() - filemtime($entry)) > 86400) {
                    @unlink($entry);
                }
            }
        } catch (\Throwable $e) {
            // Best-effort sweep only.
        }

        return $dir;
    }
}
