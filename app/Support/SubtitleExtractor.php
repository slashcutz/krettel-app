<?php

namespace App\Support;

use App\Models\Language;
use App\Models\Subtitle;
use App\Models\Video;
use App\Services\PixeldrainClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class SubtitleExtractor
{
    /**
     * Text-based subtitle codecs that ffmpeg can convert to WebVTT. Image-based
     * tracks (PGS/DVD/DVB, etc.) are skipped because they can't become text.
     */
    protected const TEXT_CODECS = [
        'subrip', 'srt', 'ass', 'ssa', 'webvtt', 'mov_text',
        'text', 'mpl2', 'subviewer', 'subviewer1', 'realtext', 'stl',
    ];

    /**
     * Extract every muxed text subtitle stream into a WebVTT file and record a
     * Subtitle row for it, storing the file on the local public disk.
     *
     * Best-effort: a broken/unsupported stream is logged and skipped, never
     * fails the caller.
     */
    public static function extractToPublicDisk(Video $video, string $absolutePath): void
    {
        foreach (static::extractTracks($absolutePath) as $track) {
            try {
                $label = $track['label'];
                $code = $track['language'] ?: LanguageCodes::code($label);

                $language = Language::firstOrCreate(
                    ['code' => $code],
                    ['name' => LanguageCodes::name($code) ?: ucfirst($label)]
                );

                $target = 'subtitles/' . $video->id . '_' . preg_replace('/[^a-z0-9_]/i', '', $code) . '.vtt';
                Storage::disk('public')->put($target, file_get_contents($track['path']));
                @unlink($track['path']);

                Subtitle::create([
                    'video_id' => $video->id,
                    'language_id' => $language->id,
                    'file_path' => $target,
                    'label' => $label,
                    'is_default' => (bool) $track['default'],
                ]);

                Log::channel('krettel')->info('[SUBTITLE-EXTRACT] Extracted muxed subtitle stream.', [
                    'video_id' => $video->id,
                    'language' => $code,
                    'label' => $label,
                    'file' => $target,
                ]);
            } catch (\Throwable $e) {
                Log::channel('krettel')->warning('[SUBTITLE-EXTRACT] Stream skipped for video ' . $video->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Extract muxed text subtitle streams, upload each to Pixeldrain, and
     * record Subtitle rows pointing at the remote file ids.
     */
    public static function extractToPixeldrain(Video $video, string $absolutePath, PixeldrainClient $pixeldrain): void
    {
        foreach (static::extractTracks($absolutePath) as $track) {
            try {
                $label = $track['label'];
                $code = $track['language'] ?: LanguageCodes::code($label);

                $language = Language::firstOrCreate(
                    ['code' => $code],
                    ['name' => LanguageCodes::name($code) ?: ucfirst($label)]
                );

                $fileId = $pixeldrain->upload(
                    $track['path'],
                    'subtitle_' . $video->id . '_' . preg_replace('/[^a-z0-9_]/i', '', $code) . '.vtt'
                );
                @unlink($track['path']);

                Subtitle::create([
                    'video_id' => $video->id,
                    'language_id' => $language->id,
                    'file_path' => 'pixeldrain://' . $fileId,
                    'label' => $label,
                    'is_default' => (bool) $track['default'],
                ]);

                Log::channel('krettel')->info('[SUBTITLE-EXTRACT] Extracted + uploaded muxed subtitle stream.', [
                    'video_id' => $video->id,
                    'language' => $code,
                    'label' => $label,
                    'pixeldrain_id' => $fileId,
                ]);
            } catch (\Throwable $e) {
                Log::channel('krettel')->warning('[SUBTITLE-EXTRACT] Pixeldrain stream skipped for video ' . $video->id . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Run one ffmpeg pass per muxed text subtitle stream, converting each to
     * WebVTT. Returns rows of ['path' => temp vtt, 'language' => 3-letter code
     * or null, 'label' => friendly name, 'default' => bool]. Temp files are
     * cleaned up by the caller's per-track handling.
     */
    protected static function extractTracks(string $absolutePath): array
    {
        $ffmpeg = config('ffmpeg.ffmpeg');
        if (! $ffmpeg || ! file_exists($absolutePath)) {
            return [];
        }

        $streams = MediaProbe::subtitleStreams($absolutePath);
        if ($streams === []) {
            return [];
        }

        $tracks = [];

        foreach ($streams as $i => $stream) {
            $codec = strtolower((string) ($stream['codec'] ?? ''));

            if (! in_array($codec, static::TEXT_CODECS, true)) {
                Log::channel('krettel')->info('[SUBTITLE-EXTRACT] Skipping non-text subtitle stream.', [
                    'codec' => $codec,
                ]);
                continue;
            }

            $tmpFile = media_temp_dir() . DIRECTORY_SEPARATOR . 'sub_' . uniqid('', true) . '.vtt';

            try {
                $process = new Process([
                    $ffmpeg, '-y', '-nostdin', '-loglevel', 'error',
                    '-i', $absolutePath,
                    '-map', '0:s:' . $i,
                    '-c:s', 'webvtt',
                    $tmpFile,
                ]);
                $process->setTimeout(300);
                $process->run();

                if (! $process->isSuccessful() || ! is_file($tmpFile) || filesize($tmpFile) < 1) {
                    @unlink($tmpFile);
                    Log::channel('krettel')->info('[SUBTITLE-EXTRACT] Subtitle stream produced no output, skipped.', [
                        'stream' => $i,
                        'codec' => $codec,
                    ]);
                    continue;
                }

                $code = $stream['language'] ?: null;
                $label = $stream['title']
                    ?: ($code ? (LanguageCodes::name($code) ?: strtoupper($code)) : ('Subtitle ' . ($i + 1)));

                $tracks[] = [
                    'path' => $tmpFile,
                    'language' => $code,
                    'label' => $label,
                    'default' => (bool) ($stream['default'] ?? false),
                ];
            } catch (\Throwable $e) {
                @unlink($tmpFile);
                Log::channel('krettel')->warning('[SUBTITLE-EXTRACT] Extract failed for stream ' . $i . ': ' . $e->getMessage());
            }
        }

        return $tracks;
    }
}
