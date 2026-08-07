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

            // 1) Push the ORIGINAL file first. This is the step that used to
            //    run inside store() and blow past the gateway's 60s request
            //    timeout (504). Running it here in the worker means the upload
            //    response returns in seconds.
            $fileId = $this->pushOriginal($pixeldrain, $absolutePath, $progressKey);

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

        return $pixeldrain->upload($absolutePath, basename($this->stagingPath));
    }
}
