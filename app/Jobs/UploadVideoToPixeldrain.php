<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\PixeldrainClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class UploadVideoToPixeldrain implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(public Video $video, public string $stagingPath) {}

    protected ?string $lastProgressPhase = null;

    public function handle(PixeldrainClient $pixeldrain): void
    {
        Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Job started for video ' . $this->video->id . ' (' . $this->video->title . ')', [
            'video_id' => $this->video->id,
            'staging_path' => $this->stagingPath,
        ]);

        $absolutePath = Storage::disk('local')->path($this->stagingPath);

        try {
            $this->video->update(['video_url' => 'processing']);

            if (! file_exists($absolutePath)) {
                throw new RuntimeException('Staging file not found: ' . $absolutePath);
            }

            $filename = basename($this->stagingPath);

            Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Uploading video to Pixeldrain...', [
                'video_id' => $this->video->id,
                'filename' => $filename,
                'size_bytes' => filesize($absolutePath),
            ]);

            $fileId = $pixeldrain->upload($absolutePath, $filename);

            $this->video->update([
                'video_url' => 'pixeldrain-remote',
                'storage_folder' => $fileId,
                'storage_provider' => 'pixeldrain',
            ]);

            Storage::disk('local')->delete($this->stagingPath);
            Cache::forget($this->progressKey());

            Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Video uploaded to Pixeldrain successfully.', [
                'video_id' => $this->video->id,
                'file_id' => $fileId,
                'staging_deleted' => true,
            ]);

            \App\Models\Notification::create([
                'user_id' => $this->video->user_id ?? \App\Models\User::first()->id,
                'title' => 'Upload Complete',
                'message' => "'{$this->video->title}' is now ready to watch.",
                'type' => 'success',
                'link' => route('video.show', $this->video->slug),
            ]);
        } catch (\Throwable $e) {
            Log::channel('krettel')->error('[PIXELDRAIN-SYNC] Failed to upload video to Pixeldrain.', [
                'video_id' => $this->video->id,
                'error' => $e->getMessage(),
                'staging_path' => $this->stagingPath,
            ]);

            $this->video->update(['video_url' => 'failed']);
            Cache::forget($this->progressKey());

            \App\Models\Notification::create([
                'user_id' => $this->video->user_id ?? \App\Models\User::first()->id,
                'title' => 'Upload Failed',
                'message' => "'{$this->video->title}' failed to upload to Pixeldrain.",
                'type' => 'error',
                'link' => route('admin.videos.index'),
            ]);

            throw $e;
        }
    }

    protected function progressKey(): string
    {
        return 'pixeldrain_upload_' . $this->video->id;
    }
}
