<?php

namespace App\Jobs;

use App\Models\Video;
use App\Support\MediaProbe;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class TranscodeVideoToHls implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200;
    public int $tries = 1;

    public function __construct(public Video $video, public string $stagingPath) {}

    public function handle(): void
    {
        $absolutePath = Storage::disk('local')->path($this->stagingPath);

        Log::info('[HLS-TRANSCODE] Starting job for video ' . $this->video->id, [
            'staging_path' => $this->stagingPath,
        ]);

        if (! file_exists($absolutePath)) {
            $this->fail('Staging file not found: ' . $absolutePath);
            return;
        }

        $audioStreams = MediaProbe::audioStreams($absolutePath);
        $videoCodec = MediaProbe::videoCodec($absolutePath);

        Log::info('[HLS-TRANSCODE] Probing done.', [
            'video_codec' => $videoCodec,
            'audio_tracks' => array_map(fn ($a) => $a['language'] ?? 'und', $audioStreams),
        ]);

        // Not a multi-audio source — fall back to the normal TeraBox path.
        if (count($audioStreams) < 2) {
            Log::info('[HLS-TRANSCODE] Source has fewer than 2 audio tracks, deferring to TeraBox.', [
                'video_id' => $this->video->id,
            ]);
            $this->video->update(['hls_status' => null, 'hls_folder' => null]);
            UploadVideoToTeraBox::dispatch($this->video, $this->stagingPath);
            return;
        }

        $outDir = storage_path('app/public/hls/' . $this->video->id);

        try {
            Storage::disk('public')->deleteDirectory('hls/' . $this->video->id);
            if (! is_dir($outDir)) {
                mkdir($outDir, 0775, true);
            }

            $names = $this->audioNames($audioStreams);
            $process = $this->buildProcess($absolutePath, $outDir, $videoCodec, $audioStreams, $names);
            $process->setTimeout($this->timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new \RuntimeException(
                    'ffmpeg exited with code ' . $process->getExitCode() . ': ' . trim($process->getErrorOutput())
                );
            }

            if (! is_file($outDir . '/index.m3u8')) {
                throw new \RuntimeException('Master playlist not produced.');
            }

            $this->finalizeMasterPlaylist($outDir, $audioStreams, $names);

            $this->video->update([
                'hls_status' => 'ready',
                'hls_folder' => 'hls/' . $this->video->id,
                'video_url' => 'hls-local',
            ]);

            Storage::disk('local')->delete($this->stagingPath);

            Log::info('[HLS-TRANSCODE] Transcode complete for video ' . $this->video->id, [
                'folder' => 'hls/' . $this->video->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('[HLS-TRANSCODE] Failed for video ' . $this->video->id, [
                'error' => $e->getMessage(),
            ]);

            $this->video->update(['hls_status' => 'failed', 'hls_folder' => null, 'video_url' => 'failed']);

            // Keep the video playable: upload the original to TeraBox (single audio).
            UploadVideoToTeraBox::dispatch($this->video, $this->stagingPath);
        }
    }

    protected function audioNames(array $audioStreams): array
    {
        $names = [];
        $seen = [];

        foreach ($audioStreams as $i => $audio) {
            $language = $audio['language'] ? preg_replace('/[^a-z0-9_]/i', '', $audio['language']) : null;
            $base = $language ?: ('audio' . $i);
            $name = $base;
            $k = 2;
            while (isset($seen[$name])) {
                $name = $base . '_' . $k++;
            }
            $seen[$name] = true;
            $names[$i] = $name;
        }

        return $names;
    }

    protected function buildProcess(string $input, string $outDir, ?string $videoCodec, array $audioStreams, array $names): Process
    {
        $args = [config('ffmpeg.ffmpeg'), '-y', '-i', $input];

        $args[] = '-map'; $args[] = '0:v:0';
        foreach ($audioStreams as $i => $audio) {
            $args[] = '-map'; $args[] = '0:a:' . $i;
        }

        // Copy the video when it's already H.264 (fast); re-encode otherwise
        // so every device (incl. iOS native HLS) can play the output.
        if (in_array($videoCodec, ['h264', 'avc1'], true)) {
            $args[] = '-c:v'; $args[] = 'copy';
        } else {
            $args[] = '-c:v'; $args[] = 'libx264';
            $args[] = '-preset'; $args[] = 'veryfast';
            $args[] = '-crf'; $args[] = '23';
            $args[] = '-pix_fmt'; $args[] = 'yuv420p';
        }

        $args[] = '-c:a'; $args[] = 'aac';
        $args[] = '-b:a'; $args[] = '160k';
        $args[] = '-ac'; $args[] = '2';

        foreach ($audioStreams as $i => $audio) {
            $language = $audio['language'] ? preg_replace('/[^a-z0-9_]/i', '', $audio['language']) : null;
            $args[] = '-metadata'; $args[] = 's:a:' . $i . '=language=' . ($language ?: 'und');
        }

        $map = 'v:0,agroup:aud,name:video';
        foreach ($names as $i => $name) {
            $map .= ' a:' . $i . ',agroup:aud,name:' . $name;
        }

        $args[] = '-var_stream_map'; $args[] = $map;
        $args[] = '-master_pl_name'; $args[] = 'index.m3u8';
        $args[] = '-hls_time'; $args[] = '6';
        $args[] = '-hls_playlist_type'; $args[] = 'vod';
        $args[] = '-hls_segment_type'; $args[] = 'mpegts';
        $args[] = '-hls_flags'; $args[] = 'independent_segments';
        $args[] = '-max_muxing_queue_size'; $args[] = '1024';
        $args[] = '-f'; $args[] = 'hls';
        $args[] = rtrim($outDir, '/\\') . '/out%v.m3u8';

        return new Process($args);
    }

    /**
     * Turn ffmpeg's raw master playlist into the clean multi-audio form:
     * friendly #EXT-X-MEDIA names and a single video #EXT-X-STREAM-INF.
     */
    protected function finalizeMasterPlaylist(string $outDir, array $audioStreams, array $names): void
    {
        $path = $outDir . '/index.m3u8';
        $content = (string) file_get_contents($path);

        // Drop the redundant audio-only variants so only the real video
        // variant appears as an #EXT-X-STREAM-INF level.
        foreach ($names as $name) {
            $uri = 'out' . $name . '.m3u8';
            $content = preg_replace(
                '/#EXT-X-STREAM-INF:[^\r\n]*\r?\n' . preg_quote($uri, '/') . '[ \t]*\r?\n?/m',
                '',
                $content
            );
        }

        // Rewrite each audio #EXT-X-MEDIA entry with a friendly display name.
        foreach ($audioStreams as $i => $audio) {
            $name = $names[$i];
            $uri = 'out' . $name . '.m3u8';
            $label = $this->languageLabel($audio['language'], $i);
            $isDefault = $audio['default'] || $i === 0;

            $content = preg_replace_callback(
                '/#EXT-X-MEDIA:TYPE=AUDIO,[^\r\n]*URI="' . preg_quote($uri, '/') . '"/',
                function ($m) use ($name, $uri, $label, $isDefault) {
                    preg_match('/GROUP-ID="([^"]+)"/', $m[0], $g);
                    $group = $g[1] ?? 'group_aud';

                    return '#EXT-X-MEDIA:TYPE=AUDIO,GROUP-ID="' . $group . '",NAME="' . $label
                        . '",DEFAULT=' . ($isDefault ? 'YES' : 'NO')
                        . ',AUTOSELECT=YES,CHANNELS="2",URI="' . $uri . '"';
                },
                $content
            );
        }

        file_put_contents($path, $content);
    }

    protected function languageLabel(?string $code, int $index): string
    {
        $map = [
            'hin' => 'Hindi', 'eng' => 'English', 'tel' => 'Telugu', 'tam' => 'Tamil',
            'kan' => 'Kannada', 'mal' => 'Malayalam', 'ben' => 'Bengali', 'mar' => 'Marathi',
            'guj' => 'Gujarati', 'pun' => 'Punjabi', 'urd' => 'Urdu', 'ori' => 'Odia',
            'asm' => 'Assamese', 'spa' => 'Spanish', 'fra' => 'French', 'deu' => 'German',
            'jpn' => 'Japanese', 'kor' => 'Korean', 'chi' => 'Chinese', 'ara' => 'Arabic',
        ];

        if ($code && isset($map[$code])) {
            return $map[$code];
        }

        return $code ? strtoupper($code) : ('Audio ' . ($index + 1));
    }

    protected function fail(string $reason): void
    {
        $this->video->update(['hls_status' => 'failed', 'hls_folder' => null, 'video_url' => 'failed']);
        Log::error('[HLS-TRANSCODE] ' . $reason, ['video_id' => $this->video->id]);
    }
}
