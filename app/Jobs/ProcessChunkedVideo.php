<?php

namespace App\Jobs;

use App\Models\Video;
use App\Support\ChunkAssembler;
use App\Support\MediaProbe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessChunkedVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(
        public Video $video,
        public string $uploadToken,
        public string $originalFilename,
        public string $storageProvider,
        public int $savedAudioCount = 0,
    ) {}

    public function handle(): void
    {
        Log::channel('krettel')->info('[CHUNKED] Stitch + route job started for video ' . $this->video->id, [
            'video_id' => $this->video->id,
            'upload_token' => $this->uploadToken,
            'storage_provider' => $this->storageProvider,
        ]);

        try {
            $chunkDir = storage_path('app/private/chunks/' . $this->uploadToken);

            $stitchedAbs = ChunkAssembler::stitch($chunkDir, $this->uploadToken, $this->originalFilename);
            $cleanName = preg_replace('/[^a-zA-Z0-9.\-_]/', '', $this->originalFilename) ?: 'video.mp4';
            $stitchedRelative = 'pending-uploads/' . $this->uploadToken . '_' . $cleanName;

            ChunkAssembler::cleanup($chunkDir);

            $container = MediaProbe::container($stitchedAbs);
            $muxedAudio = MediaProbe::audioStreamCount($stitchedAbs);
            $totalAudio = $muxedAudio + $this->savedAudioCount;

            Log::channel('krettel')->info('[CHUNKED] Processing decision for video ' . $this->video->id, [
                'video_id' => $this->video->id,
                'container' => $container,
                'muxed_audio' => $muxedAudio,
                'separate_audio' => $this->savedAudioCount,
                'total_audio' => $totalAudio,
            ]);

            if ($this->storageProvider !== 'pixeldrain' && $totalAudio >= 2) {
                $this->video->update(['hls_status' => 'processing']);
                TranscodeVideoToHls::dispatch($this->video, $stitchedRelative);
            } elseif ($this->storageProvider === 'pixeldrain') {
                $this->video->update([
                    'video_url' => 'processing',
                    'storage_provider' => 'pixeldrain',
                ]);
                ProcessPixeldrainMedia::dispatch($this->video, $stitchedRelative, $this->uploadToken);
            } elseif ($this->storageProvider === 'terabox') {
                $this->video->update([
                    'video_url' => 'processing',
                    'storage_provider' => 'terabox',
                ]);
                UploadVideoToTeraBox::dispatch($this->video, $stitchedRelative);
            } else {
                $videoPath = 'videos/' . $cleanName;
                \Illuminate\Support\Facades\Storage::disk('public')->put(
                    $videoPath,
                    file_get_contents($stitchedAbs)
                );
                @unlink($stitchedAbs);
                $this->video->update([
                    'video_url' => $videoPath,
                    'storage_provider' => 'local',
                ]);
                Log::channel('krettel')->info('[CHUNKED] Chunked video stored on local PUBLIC disk.', [
                    'video_id' => $this->video->id,
                    'video_path' => $videoPath,
                ]);
            }
        } catch (\Throwable $e) {
            Log::channel('krettel')->error('[CHUNKED] Stitch/route job failed.', [
                'video_id' => $this->video->id,
                'error' => $e->getMessage(),
            ]);
            $this->video->update(['video_url' => 'failed']);
            \App\Models\Notification::create([
                'user_id' => $this->video->user_id ?? \App\Models\User::first()->id,
                'title' => 'Upload Failed',
                'message' => "'{$this->video->title}' failed to stitch its upload.",
                'type' => 'error',
                'link' => route('admin.videos.index'),
            ]);
        }
    }
}