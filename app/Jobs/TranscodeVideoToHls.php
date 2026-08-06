<?php

namespace App\Jobs;

use App\Models\Video;
use App\Models\VideoAudioTrack;
use App\Support\LanguageCodes;
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

        $log = fn (string $msg, array $ctx = []) => Log::channel('krettel')->info(
            '[HLS-TRANSCODE] ' . $msg,
            array_merge(['video_id' => $this->video->id], $ctx)
        );

        $log('Starting job.', ['staging_path' => $this->stagingPath]);

        if (! file_exists($absolutePath)) {
            $this->fail('Staging file not found: ' . $absolutePath);
            return;
        }

        $audioStreams = MediaProbe::audioStreams($absolutePath);
        $videoCodec = MediaProbe::videoCodec($absolutePath);

        // Separately uploaded audio files (VideoAudioTrack rows) become extra
        // audio renditions, so a single-audio MKV + 1 extra file = 2 tracks.
        $extraFiles = [];
        $separateTracks = VideoAudioTrack::where('video_id', $this->video->id)->get();
        foreach ($separateTracks as $track) {
            $audioPath = Storage::disk('public')->path($track->file_path);
            if (! is_file($audioPath)) {
                $log('Separate audio file missing, skipping.', ['file' => $track->file_path]);
                continue;
            }
            $label = $track->label ?: ($track->language->name ?? null);
            $extraFiles[] = [
                'path' => $audioPath,
                'language' => $label ? LanguageCodes::code($label) : null,
                'default' => (bool) $track->is_default,
            ];
        }

        $totalAudio = count($audioStreams) + count($extraFiles);

        $log('Probe done.', [
            'video_codec' => $videoCodec,
            'muxed_audio' => count($audioStreams),
            'separate_audio' => count($extraFiles),
            'total_audio' => $totalAudio,
        ]);

        // Not enough audio sources for a multi-audio package — fall back to
        // the normal TeraBox path (single audio).
        if ($totalAudio < 2) {
            $log('Fewer than 2 audio sources — deferring to TeraBox.', [
                'muxed_audio' => count($audioStreams),
                'separate_audio' => count($extraFiles),
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

            $tracks = $this->audioTracks($audioStreams, $extraFiles);
            $names = $this->audioNames($tracks);
            $process = $this->buildProcess($absolutePath, $outDir, $videoCodec, $tracks, $names, $extraFiles);
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

            $this->finalizeMasterPlaylist($outDir, $tracks, $names);

            $this->video->update([
                'hls_status' => 'ready',
                'hls_folder' => 'hls/' . $this->video->id,
                'video_url' => 'hls-local',
            ]);

            Storage::disk('local')->delete($this->stagingPath);

            $log('Transcode complete.', [
                'folder' => 'hls/' . $this->video->id,
                'audio_tracks' => $names,
            ]);
        } catch (\Throwable $e) {
            Log::channel('krettel')->error('[HLS-TRANSCODE] Failed for video ' . $this->video->id, [
                'error' => $e->getMessage(),
            ]);

            $this->video->update(['hls_status' => 'failed', 'hls_folder' => null, 'video_url' => 'failed']);

            // Keep the video playable: upload the original to TeraBox (single audio).
            UploadVideoToTeraBox::dispatch($this->video, $this->stagingPath);
        }
    }

    /**
     * Unify muxed + separately uploaded audio sources into one list.
     *
     * Each entry: ['input' => ffmpeg input index, 'stream' => audio stream
     * index within that input, 'language' => 3-letter code, 'default' => bool]
     */
    protected function audioTracks(array $audioStreams, array $extraFiles): array
    {
        $tracks = [];

        foreach ($audioStreams as $i => $audio) {
            $tracks[] = [
                'input' => 0,
                'stream' => $i,
                'language' => $audio['language'],
                'default' => (bool) $audio['default'],
            ];
        }

        foreach ($extraFiles as $k => $file) {
            $tracks[] = [
                'input' => $k + 1,
                'stream' => 0,
                'language' => $file['language'],
                'default' => $file['default'],
            ];
        }

        return $tracks;
    }

    protected function audioNames(array $tracks): array
    {
        $names = [];
        $seen = [];

        foreach ($tracks as $i => $track) {
            $language = $track['language'] ? preg_replace('/[^a-z0-9_]/i', '', $track['language']) : null;
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

    protected function buildProcess(string $input, string $outDir, ?string $videoCodec, array $tracks, array $names, array $extraFiles): Process
    {
        $args = [config('ffmpeg.ffmpeg'), '-y', '-i', $input];

        foreach ($extraFiles as $file) {
            $args[] = '-i'; $args[] = $file['path'];
        }

        $args[] = '-map'; $args[] = '0:v:0';
        foreach ($tracks as $track) {
            $args[] = '-map'; $args[] = $track['input'] . ':a:' . $track['stream'];
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

        foreach ($tracks as $k => $track) {
            $args[] = '-metadata'; $args[] = 's:a:' . $k . '=language=' . ($track['language'] ?: 'und');
        }

        $map = 'v:0,agroup:aud,name:video';
        foreach ($names as $k => $name) {
            $map .= ' a:' . $k . ',agroup:aud,name:' . $name;
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
    protected function finalizeMasterPlaylist(string $outDir, array $tracks, array $names): void
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

        // Exactly one audio track is the DEFAULT (first, or an explicitly
        // flagged one) — matches what browsers pick without user interaction.
        $defaultIndex = 0;
        foreach ($tracks as $i => $track) {
            if ($track['default']) {
                $defaultIndex = $i;
                break;
            }
        }

        // Rewrite each audio #EXT-X-MEDIA entry with a friendly display name.
        foreach ($tracks as $i => $track) {
            $name = $names[$i];
            $uri = 'out' . $name . '.m3u8';
            $label = $this->languageLabel($track['language'], $i);
            $isDefault = ($i === $defaultIndex);

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
        Log::channel('krettel')->error('[HLS-TRANSCODE] ' . $reason, ['video_id' => $this->video->id]);
    }
}
