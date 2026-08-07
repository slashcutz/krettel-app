<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\TeraBoxClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class UploadVideoToTeraBox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(public Video $video, public string $stagingPath) {}

    protected ?string $lastProgressPhase = null;

    public function handle(TeraBoxClient $terabox): void
    {
        Log::channel('krettel')->info('[TERABOX-SYNC] Job started for video ' . $this->video->id . ' (' . $this->video->title . ')', [
            'video_id' => $this->video->id,
            'staging_path' => $this->stagingPath,
        ]);

        $absolutePath = Storage::disk('local')->path($this->stagingPath);

        $this->trackProgress('pending');
        $terabox->onProgress(function (int $bytes, int $total) {
            $this->trackProgress('uploading', $bytes, $total);
        });

        try {
            $this->video->update(['video_url' => 'processing']);

            if (! file_exists($absolutePath)) {
                throw new RuntimeException('Staging file not found: ' . $absolutePath);
            }

            // Embedded caption streams come with the source file — extract them
            // to WebVTT on the public disk so the player can offer them. This
            // runs best-effort; failure never blocks the TeraBox upload.
            try {
                \App\Support\SubtitleExtractor::extractToPublicDisk($this->video, $absolutePath);
            } catch (\Throwable $e) {
                Log::channel('krettel')->warning('[TERABOX-SYNC] Subtitle extraction skipped for video ' . $this->video->id . ': ' . $e->getMessage());
            }

            $remoteDir = config('terabox.remote_dir', '/Apps/Krettel');
            $filename = basename($this->stagingPath);

            Log::channel('krettel')->info('[TERABOX-SYNC] Authenticating + creating remote dir...', [
                'video_id' => $this->video->id,
                'remote_dir' => $remoteDir,
            ]);
            $terabox->ensureAuthenticated();
            $terabox->createDir($remoteDir);

            Log::channel('krettel')->info('[TERABOX-SYNC] Uploading video to TeraBox...', [
                'video_id' => $this->video->id,
                'filename' => $filename,
                'size_bytes' => filesize($absolutePath),
            ]);

            $remotePath = $terabox->uploadFile($absolutePath, $remoteDir, $filename);

            // Never persist the dlink — it expires after ~8h.
            // Save the permanent remote path and mark the video as terabox-hosted.
            // A fresh dlink is generated on each page view (cached ~7h).
            $this->video->update([
                'video_url' => 'terabox-remote',
                'storage_folder' => $remotePath,
            ]);

            Storage::disk('local')->delete($this->stagingPath);
            Cache::forget($this->progressKey());

            // Pre-fetch the HLS playlist + first segment so the first play is instant.
            \App\Http\Controllers\VideoController::warmStream($this->video);

            Log::channel('krettel')->info('[TERABOX-SYNC] Video uploaded to TeraBox successfully.', [
                'video_id' => $this->video->id,
                'remote_path' => $remotePath,
                'staging_deleted' => true,
            ]);

            \App\Models\Notification::create([
                'user_id' => $this->video->user_id ?? \App\Models\User::first()->id, // Fallback if no user attached to video
                'title' => 'Upload Complete',
                'message' => "'{$this->video->title}' is now ready to watch.",
                'type' => 'success',
                'link' => route('video.show', $this->video->slug),
            ]);
        } catch (\Throwable $e) {
            Log::channel('krettel')->error('[TERABOX-SYNC] Failed to upload video to TeraBox.', [
                'video_id' => $this->video->id,
                'error' => $e->getMessage(),
                'staging_path' => $this->stagingPath,
            ]);

            $this->video->update(['video_url' => 'failed']);
            Cache::forget($this->progressKey());

            \App\Models\Notification::create([
                'user_id' => $this->video->user_id ?? \App\Models\User::first()->id,
                'title' => 'Upload Failed',
                'message' => "'{$this->video->title}' failed to upload to TeraBox.",
                'type' => 'error',
                'link' => route('admin.videos.index'),
            ]);

            throw $e;
        }
    }

    protected function progressKey(): string
    {
        return 'terabox_upload_' . $this->video->id;
    }

    protected function trackProgress(string $phase, int $bytes = 0, int $total = 0): void
    {
        Cache::put($this->progressKey(), [
            'phase' => $phase,
            'bytes' => $bytes,
            'total' => $total,
            'updated_at' => now()->toDateTimeString(),
        ], now()->addHours(2));

        if ($this->lastProgressPhase !== $phase) {
            $this->lastProgressPhase = $phase;
            Log::channel('krettel')->info('[TERABOX-SYNC] Progress phase -> ' . $phase, [
                'video_id' => $this->video->id,
                'bytes' => $bytes,
                'total' => $total,
            ]);
        }
    }
}
