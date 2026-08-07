<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class VideoEncoder
{
    /**
     * Return ffmpeg args (video encoder + speed + quality) for the fastest
     * H.264 encoder actually usable on this machine. Hardware encoders (NVENC,
     * QSV, VideoToolbox) are verified with a real micro-encode; when none is
     * available it falls back to software x264 so nothing breaks.
     *
     * @param  int  $crf  CRF-equivalent quality (0-51, lower = better).
     * @param  string  $tier  libx264 preset used only on the software fallback.
     */
    public static function args(string $tier = 'veryfast', int $crf = 23): array
    {
        $encoder = self::detect();

        if ($encoder === 'h264_nvenc') {
            return ['-c:v', 'h264_nvenc', '-preset', 'p1', '-cq', (string) $crf];
        }

        if ($encoder === 'h264_qsv') {
            return ['-c:v', 'h264_qsv', '-preset', 'veryfast', '-global_quality', (string) $crf];
        }

        if ($encoder === 'h264_videotoolbox') {
            return ['-c:v', 'h264_videotoolbox', '-q:v', (string) min(70, (int) round($crf * 2.3))];
        }

        return ['-c:v', 'libx264', '-preset', $tier, '-crf', (string) $crf];
    }

    public static function detect(): string
    {
        $ffmpeg = config('ffmpeg.ffmpeg');
        if (! $ffmpeg) {
            return 'libx264';
        }

        return Cache::rememberForever('ffmpeg_h264_encoder', function () use ($ffmpeg) {
            foreach (['h264_nvenc', 'h264_qsv', 'h264_videotoolbox'] as $encoder) {
                if (self::usable($ffmpeg, $encoder)) {
                    Log::channel('krettel')->info('[ENCODER] Using hardware encoder: ' . $encoder);
                    return $encoder;
                }
            }

            Log::channel('krettel')->info('[ENCODER] No usable hardware encoder — falling back to libx264.');
            return 'libx264';
        });
    }

    /**
     * Verify an encoder is compiled in AND can actually run (i.e. a GPU is
     * present) by encoding a short testsrc clip to the null muxer. Some
     * builds ship nvenc/qsv even when the box has no GPU, so listing the
     * encoders alone is not enough.
     */
    protected static function usable(string $ffmpeg, string $encoder): bool
    {
        try {
            $process = new Process([
                $ffmpeg,
                '-hide_banner',
                '-loglevel', 'error',
                '-f', 'lavfi',
                '-i', 'testsrc=size=64x64:rate=25',
                '-t', '1',
                '-frames:v', '25',
                '-c:v', $encoder,
                '-f', 'null',
                '-',
            ]);
            $process->setTimeout(30);
            $process->run();

            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }
}
