<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class MediaProbe
{
    /**
     * Return all streams of a media file (ffprobe JSON) or null on failure.
     */
    public static function streams(string $path): ?array
    {
        if (! file_exists($path)) {
            return null;
        }

        try {
            $process = new Process([
                config('ffmpeg.ffprobe'),
                '-v', 'error',
                '-print_format', 'json',
                '-show_streams',
                $path,
            ]);

            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('[FFPROBE] Failed: ' . trim($process->getErrorOutput()));
                return null;
            }

            $data = json_decode($process->getOutput(), true);

            return $data['streams'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('[FFPROBE] Exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Count the audio streams in a media file.
     */
    public static function audioStreamCount(string $path): int
    {
        return count(static::audioStreams($path));
    }

    /**
     * Return audio stream metadata: input index, language tag, title, codec.
     */
    public static function audioStreams(string $path): array
    {
        $streams = static::streams($path);
        if (! $streams) {
            return [];
        }

        $out = [];
        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? null) !== 'audio') {
                continue;
            }

            $out[] = [
                'index' => (int) ($stream['index'] ?? count($out)),
                'codec' => $stream['codec_name'] ?? null,
                'channels' => (int) ($stream['channels'] ?? 2),
                'language' => isset($stream['tags']['language'])
                    ? strtolower((string) $stream['tags']['language'])
                    : null,
                'title' => isset($stream['tags']['title']) ? (string) $stream['tags']['title'] : null,
                'default' => ($stream['disposition']['default'] ?? 0) === 1,
            ];
        }

        return $out;
    }

    /**
     * Return container/format metadata (ffprobe -show_format) or null.
     */
    public static function format(string $path): ?array
    {
        if (! file_exists($path)) {
            return null;
        }

        try {
            $process = new Process([
                config('ffmpeg.ffprobe'),
                '-v', 'error',
                '-print_format', 'json',
                '-show_format',
                $path,
            ]);

            $process->setTimeout(60);
            $process->run();

            if (! $process->isSuccessful()) {
                return null;
            }

            $data = json_decode($process->getOutput(), true);

            return $data['format'] ?? null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Friendly container name (e.g. "mp4", "mkv", "webm") or null.
     */
    public static function container(string $path): ?string
    {
        $format = static::format($path);
        $name = $format['format_name'] ?? null;

        if (! $name) {
            return null;
        }

        $friendly = [
            'matroska,webm' => 'mkv',
            'mov,mp4,m4a,3gp,3g2,mj2' => 'mp4',
            'webm' => 'webm',
            'matroska' => 'mkv',
        ];

        foreach ($friendly as $key => $label) {
            if ($key === $name || str_contains($name, $key)) {
                return $label;
            }
        }

        return $name;
    }

    /**
     * Video codec name of the primary video stream (e.g. h264, hevc, mpeg2video).
     */
    public static function videoCodec(string $path): ?string
    {
        $streams = static::streams($path);
        if (! $streams) {
            return null;
        }

        foreach ($streams as $stream) {
            if (($stream['codec_type'] ?? null) === 'video') {
                return $stream['codec_name'] ?? null;
            }
        }

        return null;
    }
}
