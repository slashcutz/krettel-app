<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\PixeldrainClient;
use App\Services\PixeldrainMediaProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessPixeldrainMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(
        public Video $video,
        public string $stagingPath,
        public ?string $uploadToken = null,
    ) {}

    public function handle(PixeldrainMediaProcessor $processor, PixeldrainClient $pixeldrain): void
    {
        Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Background media job started for video ' . $this->video->id, [
            'video_id' => $this->video->id,
            'staging_path' => $this->stagingPath,
        ]);

        $absolutePath = Storage::disk('local')->path($this->stagingPath);
        $progressKey = $this->uploadToken ? 'pixeldrain_upload_' . $this->uploadToken : null;

        try {
            if (! file_exists($absolutePath)) {
                throw new \RuntimeException('Staging file not found: ' . $absolutePath);
            }

            // 1) Ensure the file is a web-compatible MP4. If it's an MKV or HEVC/x265,
            //    transcode it to MP4 locally before pushing it up to Pixeldrain.
            $uploadPath = $this->transcodeOriginalToMp4($absolutePath, $progressKey);

            // 2) Push the ORIGINAL (or transcoded) file first. This is the step that used to
            //    run inside store() and blow past the gateway's 60s request
            //    timeout (504). Running it here in the worker means the upload
            //    response returns in seconds.
            $fileId = $this->pushOriginal($pixeldrain, $uploadPath, $progressKey);

            $this->video->update([
                'storage_folder' => $fileId,
                'storage_provider' => 'pixeldrain',
            ]);

            if ($progressKey) {
                Cache::forget($progressKey);
            }

            Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Original video uploaded to Pixeldrain.', [
                'video_id' => $this->video->id,
                'file_id' => $fileId,
            ]);

            try {
                $processor->uploadAudioTracksToPixeldrain($this->video, $absolutePath, $pixeldrain);
            } catch (\Throwable $e) {
                // The video itself is already on Pixeldrain — never mark the
                // upload failed because an extra audio track failed.
                Log::channel('krettel')->error('[PIXELDRAIN-SYNC] Audio track split/upload failed (video kept).', [
                    'video_id' => $this->video->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                // Embedded captions come with the source file — extract them
                // and push each to Pixeldrain so the player can offer them.
                \App\Support\SubtitleExtractor::extractToPixeldrain($this->video, $absolutePath, $pixeldrain);
            } catch (\Throwable $e) {
                Log::channel('krettel')->error('[PIXELDRAIN-SYNC] Subtitle extraction/upload failed (video kept).', [
                    'video_id' => $this->video->id,
                    'error' => $e->getMessage(),
                ]);
            }

            try {
                $processor->uploadQualityVariantsToPixeldrain($this->video, $absolutePath, $pixeldrain, $progressKey);
            } catch (\Throwable $e) {
                // Same rule: variants are a bonus, never fail the upload.
                Log::channel('krettel')->error('[PIXELDRAIN-SYNC] Quality variant transcode/upload failed (video kept).', [
                    'video_id' => $this->video->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $this->video->update(['video_url' => 'pixeldrain-remote']);

            \App\Models\Notification::create([
                'user_id' => $this->video->user_id ?? \App\Models\User::first()->id,
                'title' => 'Upload Complete',
                'message' => "'{$this->video->title}' is now ready to watch.",
                'type' => 'success',
                'link' => route('video.show', $this->video->slug),
            ]);
        } catch (\Throwable $e) {
            Log::channel('krettel')->error('[PIXELDRAIN-SYNC] Background media job failed.', [
                'video_id' => $this->video->id,
                'error' => $e->getMessage(),
            ]);

            $this->video->update(['video_url' => 'failed']);

            \App\Models\Notification::create([
                'user_id' => $this->video->user_id ?? \App\Models\User::first()->id,
                'title' => 'Upload Failed',
                'message' => "'{$this->video->title}' failed to upload to Pixeldrain.",
                'type' => 'error',
                'link' => route('admin.videos.index'),
            ]);
        } finally {
            if ($progressKey) {
                Cache::forget($progressKey);
            }

            Storage::disk('local')->delete($this->stagingPath);

            Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Background media job finished for video ' . $this->video->id, [
                'video_id' => $this->video->id,
            ]);
        }
    }

    /**
     * If the source is MKV or HEVC/x265, transcode it to a web-friendly MP4 (x264)
     * before uploading. Returns the path to the file to upload (original or transcoded).
     */
    protected function transcodeOriginalToMp4(string $absolutePath, ?string $progressKey): string
    {
        $ffmpeg = config('ffmpeg.ffmpeg');
        if (! $ffmpeg) {
            return $absolutePath;
        }

        $container = \App\Support\MediaProbe::container($absolutePath);
        $codec = \App\Support\MediaProbe::videoCodec($absolutePath);
        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        $needsTranscode = ($container === 'mkv' || $ext === 'mkv' || in_array(strtolower($codec ?? ''), ['hevc', 'x265']));

        if (! $needsTranscode) {
            return $absolutePath;
        }

        $tmpFile = media_temp_dir() . DIRECTORY_SEPARATOR . 'pd_transcode_' . uniqid('', true) . '.mp4';

        if ($progressKey) {
            Cache::put($progressKey, [
                'uploaded' => 0, 'total' => 0, 'percent' => 100,
                'phase' => 'Converting MKV/x265 to MP4...',
                'updated_at' => now()->toIso8601String(),
            ], now()->addMinutes(90));
        }

        Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Converting MKV/x265 original to MP4.', ['video_id' => $this->video->id]);

        $args = [
            $ffmpeg, '-y', '-nostdin', '-loglevel', 'error',
            '-i', $absolutePath,
            '-map', '0:v:0',
            '-c:v', 'libx264',
            '-preset', 'fast',
            '-crf', '24',
            '-map', '0:a?',
            '-c:a', 'aac',
            '-b:a', '192k',
            '-movflags', '+faststart',
            $tmpFile,
        ];

        $process = new \Symfony\Component\Process\Process($args);
        $process->setTimeout(14400); // Allow up to 4 hours for full movie transcode
        $process->run();

        if ($process->isSuccessful() && is_file($tmpFile) && filesize($tmpFile) > 0) {
            return $tmpFile;
        }

        Log::channel('krettel')->warning('[PIXELDRAIN-SYNC] Transcoding failed, falling back to original file.', [
            'error' => trim($process->getErrorOutput())
        ]);
        @unlink($tmpFile);

        return $absolutePath;
    }

    /**
     * Upload the original video file to Pixeldrain while mirroring live
     * bytes/percent/speed/ETA into the progress cache keyed by upload token.
     * The popup and the mobile inline bar poll that key so users see real
     * MB/s + remaining time during the push instead of a frozen bar.
     */
    protected function pushOriginal(PixeldrainClient $pixeldrain, string $absolutePath, ?string $progressKey): string
    {
        if ($progressKey) {
            $lastTick = 0.0;
            $lastBytes = 0;
            $lastWrite = 0.0;

            $pixeldrain->onProgress(function ($uploaded, $total) use ($progressKey, &$lastTick, &$lastBytes, &$lastWrite) {
                $now = microtime(true);
                $dt = ($lastTick > 0) ? ($now - $lastTick) : 0.0;
                $speed = ($dt > 0 && $uploaded >= $lastBytes) ? (($uploaded - $lastBytes) / $dt) : 0.0;
                $eta = ($speed > 0 && $total > $uploaded) ? (($total - $uploaded) / $speed) : 0.0;

                $lastTick = $now;
                $lastBytes = $uploaded;

                // Throttle cache writes to ~once/second so a multi-GB push does
                // not hammer the cache store with per-chunk writes.
                if (($now - $lastWrite) >= 1.0 || $uploaded >= $total) {
                    $lastWrite = $now;

                    Cache::put($progressKey, [
                        'uploaded' => (int) $uploaded,
                        'total' => (int) $total,
                        'percent' => $total > 0 ? (int) round(($uploaded / $total) * 100) : 0,
                        'phase' => 'Pushing original file to Pixeldrain…',
                        'offset' => (int) $uploaded,
                        'chunked_speed' => $speed,
                        'chunked_eta' => $eta,
                        'updated_at' => now()->toIso8601String(),
                    ], now()->addMinutes(90));
                }
            });
        }

        $pushStart = microtime(true);
        $fileId = $pixeldrain->upload($absolutePath, basename($this->stagingPath));
        $pushElapsed = microtime(true) - $pushStart;
        $pushSize = (int) @filesize($absolutePath);

        Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Original video push finished.', [
            'video_id' => $this->video->id,
            'file_id' => $fileId,
            'size_mb' => round($pushSize / 1048576, 2),
            'elapsed_s' => round($pushElapsed, 1),
            'speed_MBps' => $pushElapsed > 0 ? round(($pushSize / 1048576) / $pushElapsed, 1) : 0,
        ]);

        return $fileId;
    }
}
