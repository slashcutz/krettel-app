<?php

namespace App\Services;

use App\Models\Language;
use App\Models\Video;
use App\Models\VideoAudioTrack;
use App\Support\LanguageCodes;
use App\Support\MediaProbe;
use App\Support\VideoEncoder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class PixeldrainMediaProcessor
{
    /**
     * Split every audio stream out of a Pixeldrain-hosted video, upload each
     * track to Pixeldrain, and record the file IDs so the player can re-mux
     * the chosen language on demand.
     *
     * Separately uploaded audio files (already saved as local tracks) are
     * pushed to Pixeldrain too.
     */
    public function uploadAudioTracksToPixeldrain(Video $video, string $absolutePath, PixeldrainClient $pixeldrain): void
    {
        $ffmpeg = config('ffmpeg.ffmpeg');
        if (! $ffmpeg) {
            throw new \RuntimeException('FFmpeg not configured — cannot split audio tracks.');
        }

        // 1) Muxed audio streams inside the source file.
        //    Split ALL streams in a single ffmpeg pass (one decode, no N restarts),
        //    and skip re-encoding entirely when every track is already AAC/MP3.
        $streams = MediaProbe::audioStreams($absolutePath);

        if ($streams !== []) {
            $allCopyable = collect($streams)->every(fn ($s) => in_array(strtolower($s['codec'] ?? ''), ['aac', 'mp3'], true));
            $tmpDir = media_temp_dir() . DIRECTORY_SEPARATOR . 'pd_audio_' . uniqid('', true);
            @mkdir($tmpDir, 0777, true);

            $processArgs = [$ffmpeg, '-y', '-nostdin', '-loglevel', 'error', '-i', $absolutePath];
            $outputFiles = [];

            foreach ($streams as $i => $stream) {
                $outFile = $tmpDir . DIRECTORY_SEPARATOR . 'audio_' . $i . '.m4a';
                $outputFiles[$i] = $outFile;

                $processArgs[] = '-map';
                $processArgs[] = '0:a:' . $i;
                if ($allCopyable) {
                    // Remux-only: near-instant, keeps original quality.
                    $processArgs[] = '-c:a';
                    $processArgs[] = 'copy';
                } else {
                    $processArgs[] = '-c:a';
                    $processArgs[] = 'aac';
                    $processArgs[] = '-b:a';
                    $processArgs[] = '160k';
                    $processArgs[] = '-ac';
                    $processArgs[] = '2';
                }
                $processArgs[] = '-f';
                $processArgs[] = 'mp4';
                $processArgs[] = $outFile;
            }

            try {
                $process = new Process($processArgs);
                $process->setTimeout(3600);
                $process->run();

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException('ffmpeg split failed: ' . trim($process->getErrorOutput()));
                }

                foreach ($streams as $i => $stream) {
                    $tmpFile = $outputFiles[$i];
                    if (! is_file($tmpFile) || filesize($tmpFile) < 1) {
                        throw new \RuntimeException('ffmpeg produced no audio output for stream ' . $i . '.');
                    }

                    $label = $stream['title']
                        ?: ($stream['language'] ? strtoupper($stream['language']) : ('Audio ' . ($i + 1)));
                    $code = $stream['language'] ?: LanguageCodes::code($label);

                    $audioId = $pixeldrain->upload($tmpFile, $label . '.m4a');

                    $language = Language::firstOrCreate(
                        ['code' => $code],
                        ['name' => ucfirst($label)]
                    );

                    VideoAudioTrack::create([
                        'video_id' => $video->id,
                        'language_id' => $language->id,
                        'label' => $label,
                        'file_path' => $audioId,
                        'storage' => 'pixeldrain',
                        'is_default' => (bool) ($stream['default'] ?? false),
                    ]);

                    Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Audio track split + uploaded.', [
                        'video_id' => $video->id,
                        'stream' => $i,
                        'label' => $label,
                        'pixeldrain_id' => $audioId,
                    ]);
                }
            } finally {
                foreach ($outputFiles as $outFile) {
                    @unlink($outFile);
                }
                @rmdir($tmpDir);
            }
        }

        // 2) Separately uploaded audio files already recorded as local tracks.
        $localTracks = VideoAudioTrack::where('video_id', $video->id)
            ->where('storage', 'local')
            ->get();

        foreach ($localTracks as $track) {
            $localPath = Storage::disk('public')->path($track->file_path);

            if (! is_file($localPath)) {
                Log::channel('krettel')->warning('[PIXELDRAIN-SYNC] Separate audio file missing, skipping.', [
                    'video_id' => $video->id,
                    'file' => $track->file_path,
                ]);
                continue;
            }

            $audioId = $pixeldrain->upload($localPath, basename($localPath));
            $oldPath = $track->file_path;

            $track->update([
                'file_path' => $audioId,
                'storage' => 'pixeldrain',
            ]);

            Storage::disk('public')->delete($oldPath);

            Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Separate audio uploaded to Pixeldrain.', [
                'video_id' => $video->id,
                'label' => $track->label,
                'pixeldrain_id' => $audioId,
            ]);
        }
    }

    /**
     * Transcode lower-resolution H.264 variants (720p/480p) of a Pixeldrain
     * video and upload each to Pixeldrain, so the player can offer real video
     * quality options. Each variant carries the default audio track (copied
     * when already AAC/MP3, otherwise re-encoded to AAC) and is H.264, which
     * also guarantees browser playback even when the original file is HEVC/MKV.
     *
     * Runs the encodes in parallel (multicore) and never fails the main
     * upload — any failed variant is logged and skipped. While ffmpeg runs,
     * live per-variant percent is written into the sync progress cache so a
     * poller can show real transcoding progress instead of a frozen 100%.
     */
    public function uploadQualityVariantsToPixeldrain(Video $video, string $absolutePath, PixeldrainClient $pixeldrain, ?string $progressKey = null): void
    {
        $ffmpeg = config('ffmpeg.ffmpeg');
        if (! $ffmpeg) {
            return;
        }

        $probe = MediaProbe::streams($absolutePath);
        if (! is_array($probe) || $probe === []) {
            return;
        }

        $videoHeight = 0;
        $defaultAudioIndex = null;
        $defaultAudioCodec = null;
        $audioIdx = 0;

        foreach ($probe as $stream) {
            if (($stream['codec_type'] ?? null) === 'video' && $videoHeight === 0) {
                $videoHeight = (int) ($stream['height'] ?? 0);
            }
            if (($stream['codec_type'] ?? null) === 'audio') {
                if ($defaultAudioIndex === null && ($stream['disposition']['default'] ?? 0) === 1) {
                    $defaultAudioIndex = $audioIdx;
                    $defaultAudioCodec = strtolower((string) ($stream['codec'] ?? ''));
                }
                $audioIdx++;
            }
        }

        if ($videoHeight < 1) {
            return;
        }

        $targets = array_filter([720, 480], fn ($h) => $h < $videoHeight);
        if ($targets === []) {
            return;
        }

        $format = MediaProbe::format($absolutePath);
        $durationUs = (int) round(((float) ($format['duration'] ?? 0)) * 1000000);

        $jobs = [];
        foreach ($targets as $height) {
            $tmpFile = media_temp_dir() . DIRECTORY_SEPARATOR . 'pd_q_' . uniqid('', true) . '.mp4';

            $args = [
                $ffmpeg, '-y', '-nostdin', '-loglevel', 'error',
                '-progress', 'pipe:1',
                '-i', $absolutePath,
                '-map', '0:v:0',
                '-vf', 'scale=-2:' . $height,
            ];
            $args = array_merge($args, VideoEncoder::args('ultrafast', 26));
            $args[] = '-movflags';
            $args[] = '+faststart';

            if ($defaultAudioIndex !== null) {
                $args[] = '-map';
                $args[] = '0:a:' . $defaultAudioIndex;
                if (in_array($defaultAudioCodec, ['aac', 'mp3'], true)) {
                    // Copy already-compatible audio — no re-encode needed.
                    $args[] = '-c:a';
                    $args[] = 'copy';
                } else {
                    $args[] = '-c:a';
                    $args[] = 'aac';
                    $args[] = '-b:a';
                    $args[] = '160k';
                    $args[] = '-ac';
                    $args[] = '2';
                }
            } else {
                $args[] = '-an';
            }

            $args[] = '-f';
            $args[] = 'mp4';
            $args[] = $tmpFile;

            $process = new Process($args);
            $process->setTimeout(3600);
            $process->start();

            $jobs[] = [
                'height' => $height,
                'tmpFile' => $tmpFile,
                'process' => $process,
                'percent' => 0,
                'outTimeUs' => 0,
            ];
        }

        // Drain ffmpeg -progress output while the encodes run and mirror live
        // per-variant percent into the progress cache.
        $finished = 0;
        while ($finished < count($jobs)) {
            $finished = 0;
            foreach ($jobs as $idx => $job) {
                $process = $job['process'];
                $chunk = $process->getIncrementalOutput();
                if ($chunk !== '') {
                    $jobs[$idx]['outTimeUs'] = $this->parseFfmpegProgressUs($chunk, $job['outTimeUs']);
                }
                if (! $process->isRunning()) {
                    $jobs[$idx]['percent'] = 100;
                    $finished++;
                } elseif ($durationUs > 0 && $jobs[$idx]['outTimeUs'] > 0) {
                    $jobs[$idx]['percent'] = min(99, (int) (($jobs[$idx]['outTimeUs'] / $durationUs) * 100));
                }
            }
            $this->writeVariantProgress($progressKey, $jobs);
            usleep(300000);
        }

        foreach ($jobs as $job) {
            /** @var \Symfony\Component\Process\Process $process */
            $process = $job['process'];
            $tmpFile = $job['tmpFile'];
            $height = $job['height'];

            try {
                $process->wait();

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException('ffmpeg variant transcode failed: ' . trim($process->getErrorOutput()));
                }
                if (! is_file($tmpFile) || filesize($tmpFile) < 1) {
                    throw new \RuntimeException('ffmpeg produced no output for ' . $height . 'p.');
                }

                $label = $height . 'p';

                if ($progressKey) {
                    Cache::put($progressKey, [
                        'uploaded' => 0,
                        'total' => 0,
                        'percent' => 100,
                        'phase' => 'Uploading ' . $label . ' variant…',
                        'updated_at' => now()->toIso8601String(),
                    ], now()->addMinutes(90));
                }

                $fileId = $pixeldrain->upload($tmpFile, $label . '.mp4');

                \App\Models\VideoQualityVariant::create([
                    'video_id' => $video->id,
                    'file_path' => $fileId,
                    'label' => $label,
                    'height' => $height,
                    'storage' => 'pixeldrain',
                    'is_default' => false,
                ]);

                Log::channel('krettel')->info('[PIXELDRAIN-SYNC] Quality variant transcoded + uploaded.', [
                    'video_id' => $video->id,
                    'quality' => $label,
                    'pixeldrain_id' => $fileId,
                ]);
            } catch (\Throwable $e) {
                Log::channel('krettel')->warning('[PIXELDRAIN-SYNC] Quality variant skipped (' . $height . 'p) for video ' . $video->id . ': ' . $e->getMessage());
            } finally {
                @unlink($tmpFile);
            }
        }
    }

    /**
     * Extract the latest out_time_us from a chunk of ffmpeg -progress output.
     */
    protected function parseFfmpegProgressUs(string $chunk, int $current): int
    {
        foreach (preg_split('/\R/', $chunk) as $line) {
            $line = trim($line);
            if (preg_match('/^out_time_us=(\d+)$/', $line, $m)) {
                $current = (int) $m[1];
            }
        }

        return $current;
    }

    /**
     * Write a human-readable combined transcoding progress string into the
     * sync progress cache (e.g. "Transcoding variants — 720p 45%, 480p 60%").
     */
    protected function writeVariantProgress(?string $progressKey, array $jobs): void
    {
        if (! $progressKey) {
            return;
        }

        $parts = [];
        $allDone = true;
        foreach ($jobs as $job) {
            $parts[] = $job['height'] . 'p ' . $job['percent'] . '%';
            if ($job['percent'] < 100) {
                $allDone = false;
            }
        }

        if ($allDone) {
            return;
        }

        Cache::put($progressKey, [
            'uploaded' => 0,
            'total' => 0,
            'percent' => 100,
            'phase' => 'Transcoding variants — ' . implode(', ', $parts),
            'updated_at' => now()->toIso8601String(),
        ], now()->addMinutes(90));
    }
}
