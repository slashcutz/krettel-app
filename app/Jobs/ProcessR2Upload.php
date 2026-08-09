<?php

namespace App\Jobs;

use App\Models\Video;
use App\Services\R2Presigner;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Handles an R2 direct-upload session.
 *
 * The browser PUT every chunk straight into Cloudflare R2 (fast, even from
 * far away). This job runs in the queue worker and:
 *
 *   1. downloads all chunks R2 -> the local chunk dir (fast server-to-server),
 *   2. deletes the R2 objects (R2 is only a staging relay, never permanent),
 *   3. dispatches ProcessChunkedVideo which stitches + pushes to the real
 *      destination (Pixeldrain / TeraBox) exactly as before.
 */
class ProcessR2Upload implements ShouldQueue
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
        $token = $this->uploadToken;
        $cacheKey = 'r2_upload_' . $token;
        $progressKey = 'pixeldrain_upload_' . $token;

        Log::channel('krettel')->info('[R2] Relay job started for video ' . $this->video->id, [
            'video_id' => $this->video->id,
            'upload_token' => $token,
            'storage_provider' => $this->storageProvider,
        ]);

        try {
            $session = Cache::get($cacheKey);
            if (! is_array($session)) {
                throw new \RuntimeException('R2 upload session not found for token ' . $token);
            }

            $total = (int) ($session['total_chunks'] ?? 0);
            $done = $session['done'] ?? [];
            if ($total <= 0) {
                throw new \RuntimeException('Invalid R2 session chunk count for ' . $token);
            }

            for ($i = 0; $i < $total; $i++) {
                if (empty($done[$i])) {
                    throw new \RuntimeException('R2 chunk ' . $i . ' was never uploaded (session incomplete).');
                }
            }

            $presigner = new R2Presigner();
            if (! $presigner->isConfigured()) {
                throw new \RuntimeException('R2 is not configured, cannot relay upload.');
            }

            $chunkDir = storage_path('app/private/chunks/' . $token);
            if (! is_dir($chunkDir)) {
                mkdir($chunkDir, 0777, true);
            }
            file_put_contents($chunkDir . '/.total', (string) $total);

            $client = new Client(['timeout' => 3600, 'connect_timeout' => 30]);

            $downloadStart = microtime(true);
            $downloadedBytes = 0;

            for ($i = 0; $i < $total; $i++) {
                $dest = $chunkDir . '/' . $i;
                if (file_exists($dest)) {
                    $downloadedBytes += (int) filesize($dest);
                    continue;
                }

                $url = $presigner->presignGet($presigner->chunkKey($token, $i), 3600);
                $client->request('GET', $url, ['sink' => $dest]);

                if (! file_exists($dest)) {
                    throw new \RuntimeException('Failed to download R2 chunk ' . $i);
                }
                $downloadedBytes += (int) filesize($dest);

                $percent = (int) round((($i + 1) / $total) * 100);
                Cache::put($progressKey, [
                    'uploaded' => $downloadedBytes,
                    'total' => $downloadedBytes,
                    'bytes' => $downloadedBytes,
                    'percent' => $percent,
                    'phase' => 'Downloaded from Cloudflare ' . ($i + 1) . '/' . $total . '…',
                    'updated_at' => now()->toIso8601String(),
                ], now()->addMinutes(90));

                Log::channel('krettel')->info('[R2] Chunk relayed from Cloudflare.', [
                    'video_id' => $this->video->id,
                    'upload_token' => $token,
                    'chunk_index' => $i,
                    'chunk_mb' => round((int) @filesize($dest) / 1048576, 2),
                ]);
            }

            $downloadElapsed = microtime(true) - $downloadStart;
            Log::channel('krettel')->info('[R2] All chunks relayed from Cloudflare to the server.', [
                'video_id' => $this->video->id,
                'upload_token' => $token,
                'total_chunks' => $total,
                'size_mb' => round($downloadedBytes / 1048576, 2),
                'elapsed_s' => round($downloadElapsed, 1),
                'speed_MBps' => $downloadElapsed > 0 ? round(($downloadedBytes / 1048576) / $downloadElapsed, 1) : 0,
            ]);

            // Chunks now live on the server volume — clean up the R2 staging
            // copy (best effort) so the 10GB free bucket never fills up.
            $this->deleteR2Objects($presigner, $client, $token, $done);

            // Forget the R2 session + any relay progress. The Pixeldrain push
            // writes its own progress under the same key afterwards.
            Cache::forget($cacheKey);
            Cache::forget($progressKey);

            ProcessChunkedVideo::dispatch($this->video, $token, $this->originalFilename, $this->storageProvider, $this->savedAudioCount);
        } catch (\Throwable $e) {
            Log::channel('krettel')->error('[R2] Relay job failed.', [
                'video_id' => $this->video->id,
                'upload_token' => $token,
                'error' => $e->getMessage(),
            ]);

            $this->video->update(['video_url' => 'failed']);

            \App\Models\Notification::create([
                'user_id' => $this->video->user_id ?? \App\Models\User::first()->id,
                'title' => 'Upload Failed',
                'message' => "'{$this->video->title}' failed to move from Cloudflare to the server.",
                'type' => 'error',
                'link' => route('admin.videos.index'),
            ]);
        }
    }

    protected function deleteR2Objects(R2Presigner $presigner, Client $client, string $token, array $done): void
    {
        foreach (array_keys($done) as $index) {
            try {
                $url = $presigner->presignDelete($presigner->chunkKey($token, (int) $index), 300);
                $client->request('DELETE', $url, ['timeout' => 30]);
            } catch (\Throwable $e) {
                Log::channel('krettel')->warning('[R2] Failed to delete staging object, it will expire.', [
                    'upload_token' => $token,
                    'chunk_index' => $index,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
