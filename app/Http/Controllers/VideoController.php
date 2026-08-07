<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\Video;
use App\Services\TeraBoxClient;

class VideoController extends Controller
{
    public function show($slug)
    {
        $video = Video::with('category', 'subtitles.language')->where('slug', $slug)->firstOrFail();

        $relatedVideos = Video::where('category_id', $video->category_id)
            ->where('id', '!=', $video->id)
            ->inRandomOrder()
            ->take(5)
            ->get();

        $recentlyWatched = collect();
        $scope = \App\Support\DeviceContext::scope();

        if ($scope['user_id']) {
            $recentlyWatched = \App\Models\WatchHistory::with('video')
                ->where('user_id', $scope['user_id'])
                ->where('video_id', '!=', $video->id)
                ->latest('updated_at')
                ->take(5)
                ->get()
                ->pluck('video')
                ->filter();
        } else {
            $recentlyWatched = \App\Models\WatchHistory::with('video')
                ->whereNull('user_id')
                ->where('device_id', $scope['device_id'])
                ->where('video_id', '!=', $video->id)
                ->latest('updated_at')
                ->take(5)
                ->get()
                ->pluck('video')
                ->filter();
        }

        try {
            \App\Models\WatchHistory::updateOrCreate(
                array_filter([
                    'video_id' => $video->id,
                    'user_id' => $scope['user_id'],
                    'device_id' => $scope['user_id'] ? null : $scope['device_id'],
                ]),
                [
                    'device_type' => $scope['device_type'],
                    'ip_address' => $scope['ip_address'],
                    'updated_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[WATCH] Could not record history: ' . $e->getMessage());
        }

        if ($recentlyWatched->isEmpty()) {
            $recentlyWatched = Video::inRandomOrder()->take(5)->get();
        }

        $inMyList = \App\Models\Favorite::where('video_id', $video->id)
            ->where(fn ($q) => \App\Support\DeviceContext::contextQuery($q))
            ->exists();

        $streamUrl = static::resolveStreamUrl($video);

        // Advertise the correct <source> mime so browsers pick the right
        // demuxer (HLS playlist vs MP4 vs MKV) instead of guessing from a
        // hardcoded video/mp4, which causes slow seeks on large files.
        $streamMime = 'video/mp4';
        if ($video->hls_status === 'ready' && $video->hls_folder) {
            $streamMime = 'application/x-mpegURL';
        } elseif ($video->video_url === 'terabox-remote') {
            $streamMime = 'application/x-mpegURL';
        } elseif ($video->storage_provider === 'pixeldrain' && $video->storage_folder) {
            $fileName = static::pixeldrainFileName($video->storage_folder);
            if ($fileName) {
                $streamMime = static::mimeForPath($fileName);
            }
        }

        // Subtitle tracks for the player: local files are served straight from
        // the public disk, Pixeldrain-hosted ones through the proxy route.
        $subtitleTracks = $video->subtitles->map(function (\App\Models\Subtitle $subtitle) use ($video) {
            return [
                'id' => $subtitle->id,
                'label' => $subtitle->label ?: ($subtitle->language->name ?? 'Captions'),
                'srclang' => $subtitle->language->code ?? 'en',
                'src' => str_starts_with($subtitle->file_path, 'pixeldrain://')
                    ? route('video.stream.pixeldrain.subtitle', ['video' => $video->id, 'subtitle' => $subtitle->id])
                    : \Illuminate\Support\Facades\Storage::disk('public')->url($subtitle->file_path),
                'default' => (bool) $subtitle->is_default,
            ];
        })->values();

        $pixeldrainAudioTracks = collect();
        $pixeldrainQualityVariants = collect();
        if ($video->storage_provider === 'pixeldrain') {
            $pixeldrainAudioTracks = \App\Models\VideoAudioTrack::where('video_id', $video->id)
                ->where('storage', 'pixeldrain')
                ->orderByDesc('is_default')
                ->orderBy('id')
                ->get();
            $pixeldrainQualityVariants = \App\Models\VideoQualityVariant::where('video_id', $video->id)
                ->where('storage', 'pixeldrain')
                ->orderByDesc('height')
                ->get();
        }

        return view('frontend.video.show', compact('video', 'relatedVideos', 'recentlyWatched', 'streamUrl', 'streamMime', 'inMyList', 'pixeldrainAudioTracks', 'pixeldrainQualityVariants', 'subtitleTracks'));
    }

    /**
     * Resolve the playable stream URL for a video.
     *
     * For TeraBox-hosted videos the app serves an HLS playlist through the
     * local proxy (route video.stream), because raw TeraBox dlinks are
     * single-use and reset after the first connection. Everything else just
     * uses the stored URL.
     */
    public static function resolveStreamUrl(Video $video): ?string
    {
        // Multi-audio videos are served from the locally transcoded HLS package.
        // A root-relative URL keeps playback on the same origin regardless of
        // the configured APP_URL (dev server port differs from APP_URL).
        if ($video->hls_status === 'ready' && $video->hls_folder) {
            return '/stream/hls/' . $video->id . '/index.m3u8';
        }

        // Pixeldrain videos are proxied through our server (no transcode —
        // original resolution plays as-is).
        if ($video->storage_provider === 'pixeldrain' && $video->storage_folder) {
            return Route::has('video.stream.pixeldrain')
                ? route('video.stream.pixeldrain', ['video' => $video->id])
                : null;
        }

        if ($video->video_url !== 'terabox-remote' || ! $video->storage_folder) {
            return $video->video_url;
        }

        return Route::has('video.stream')
            ? route('video.stream', ['video' => $video->id])
            : null;
    }

    /**
     * Serve the locally transcoded HLS package (master playlist + segments).
     */
    public function hls(Video $video, string $path)
    {
        if ($video->hls_status !== 'ready' || ! $video->hls_folder) {
            abort(404);
        }

        $baseReal = realpath(storage_path('app/public/' . $video->hls_folder));
        $fullReal = realpath($baseReal . DIRECTORY_SEPARATOR . $path);

        if ($baseReal === false || $fullReal === false || $fullReal === $baseReal) {
            abort(404);
        }

        if (! str_starts_with($fullReal, $baseReal . DIRECTORY_SEPARATOR) || ! is_file($fullReal)) {
            abort(404);
        }

        $mime = match (strtolower(pathinfo($fullReal, PATHINFO_EXTENSION))) {
            'm3u8' => 'application/vnd.apple.mpegurl',
            'ts' => 'video/mp2t',
            default => 'application/octet-stream',
        };

        return response()->file($fullReal, [
            'Content-Type' => $mime,
            'Cache-Control' => 'public, max-age=604800',
        ]);
    }

    /**
     * Serve an HLS playlist for a TeraBox-hosted video.
     *
     * Accepts an optional `?q=480|720` query to select the streaming quality.
     * If the requested quality is not available (free accounts are capped at
     * 480p), it silently falls back to 480p.
     *
     * The TeraBox streaming endpoint requires a full session verification
     * (~2s of round-trips), so the rewritten playlist is cached for 30 minutes
     * and only refetched on expiry. Segment URLs are stable/reusable (verified
     * by fetching the same segment twice), which makes this caching safe.
     */
    public function stream(Video $video)
    {
        if ($video->video_url !== 'terabox-remote' || ! $video->storage_folder) {
            abort(404);
        }

        $quality = (string) request()->query('q', '480');
        if (! in_array($quality, ['480', '720'], true)) {
            $quality = '480';
        }

        $rewritten = Cache::remember('terabox_hls_' . $video->id . '_' . $quality, now()->addMinutes(30), function () use ($video, $quality) {
            $terabox = app(TeraBoxClient::class);

            try {
                $playlist = $terabox->getHlsPlaylist($video->storage_folder, 'M3U8_AUTO_' . $quality);
            } catch (\Throwable $e) {
                if ($quality === '720') {
                    // 720p not available (free tier cap) — degrade to 480p.
                    Log::info('[TERABOX-HLS] 720p unavailable, falling back to 480p.', [
                        'video_id' => $video->id,
                        'error' => $e->getMessage(),
                    ]);

                    try {
                        $playlist = $terabox->getHlsPlaylist($video->storage_folder, 'M3U8_AUTO_480');
                    } catch (\Throwable $fallback) {
                        Log::warning('[TERABOX-HLS] Failed to fetch 480p playlist.', [
                            'video_id' => $video->id,
                            'error' => $fallback->getMessage(),
                        ]);

                        abort(502, 'Stream unavailable. Please try again later.');
                    }
                } else {
                    Log::warning('[TERABOX-HLS] Failed to fetch playlist.', [
                        'video_id' => $video->id,
                        'error' => $e->getMessage(),
                    ]);

                    abort(502, 'Stream unavailable. Please try again later.');
                }
            }

            // Pre-fetch the first segment so the player starts instantly from
            // the local cache instead of waiting on the TeraBox CDN.
            static::warmFirstSegment($video, $terabox, $playlist);

            return static::rewritePlaylist($video, $playlist);
        });

        return response($rewritten, 200, [
            'Content-Type' => 'application/vnd.apple.mpegurl',
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }

    /**
     * Proxy a single HLS segment from TeraBox to the player.
     */
    public function segment(Video $video, string $u)
    {
        $url = static::decodeSegmentUrl($u);

        if (! is_string($url) || ! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
            abort(400);
        }

        $data = Cache::store('file')->get('terabox_seg_' . md5($url));

        if ($data === null) {
            try {
                $data = app(TeraBoxClient::class)->fetchSegment($url);
            } catch (\Throwable $e) {
                Log::warning('[TERABOX-SEG] Failed to fetch segment.', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage(),
                ]);

                abort(502);
            }
        }

        return response($data['body'], $data['status'], [
            'Content-Type' => $data['type'],
            'Cache-Control' => 'public, max-age=1800',
        ]);
    }

    /**
     * Warm the HLS playlist + first segment cache for a video.
     *
     * Called right after an upload completes so the very first playback is
     * near-instant (no TeraBox round-trips on the player's critical path).
     */
    public static function warmStream(Video $video): void
    {
        if ($video->video_url !== 'terabox-remote' || ! $video->storage_folder) {
            return;
        }

        $terabox = app(TeraBoxClient::class);

        foreach (['480', '720'] as $quality) {
            try {
                $playlist = $terabox->getHlsPlaylist($video->storage_folder, 'M3U8_AUTO_' . $quality);

                Cache::put('terabox_hls_' . $video->id . '_' . $quality, static::rewritePlaylist($video, $playlist), now()->addMinutes(30));
                static::warmFirstSegment($video, $terabox, $playlist);
            } catch (\Throwable $e) {
                Log::debug('[TERABOX-HLS] Pre-warm skipped for video ' . $video->id . ' at ' . $quality . 'p: ' . $e->getMessage());
            }
        }
    }

    protected static function rewritePlaylist(Video $video, string $playlist): string
    {
        return preg_replace_callback('/^https?:\/\/\S+$/m', function ($m) use ($video) {
            // Relative path so hls.js resolves it against the manifest's real
            // host (127.0.0.1:8000 etc.) — never bakes in APP_URL / a port.
            return parse_url(route('video.segment', [
                'video' => $video->id,
                'u' => static::encodeSegmentUrl($m[0]),
            ]), PHP_URL_PATH);
        }, $playlist);
    }

    protected static function warmFirstSegment(Video $video, TeraBoxClient $terabox, string $playlist): void
    {
        if (! preg_match('/^https?:\/\/\S+$/m', $playlist, $m)) {
            return;
        }

        try {
            $first = $terabox->fetchSegment($m[0]);
            if ($first['status'] === 200 && $first['body'] !== '') {
                // Segment bytes are binary; the DB cache column is utf8mb4 text,
                // so bodies go through the file store (auto-prunes with TTL).
                Cache::store('file')->put('terabox_seg_' . md5($m[0]), $first, now()->addMinutes(30));
            }
        } catch (\Throwable $e) {
            Log::debug('[TERABOX-HLS] First-segment pre-warm failed for video ' . $video->id . ': ' . $e->getMessage());
        }
    }

    protected static function encodeSegmentUrl(string $url): string
    {
        return rtrim(strtr(base64_encode($url), '+/', '-_'), '=');
    }

    protected static function decodeSegmentUrl(string $token): ?string
    {
        $decoded = base64_decode(strtr($token, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * Stream a Pixeldrain-hosted video through our server.
     *
     * Pixeldrain has no transcode tier — the uploaded file is served at its
     * original resolution, so upload 720p/1080p MP4 and the player gets real
     * 720p+. Requests are proxied (the browser never talks to pixeldrain
     * directly, which sidesteps free-tier hotlink protection and ISP blocks)
     * and upstream Range requests are forwarded so seeking works.
     */
    public function streamPixeldrain(Video $video)
    {
        if ($video->storage_provider !== 'pixeldrain' || ! $video->storage_folder) {
            abort(404);
        }

        // The pixeldrain file id carries no extension, so resolve the real
        // container name once (cached) and serve the matching Content-Type.
        // Serving an MKV as video/mp4 makes browsers pick the wrong demuxer
        // and seek slowly (or re-download from the start) on large files.
        $fileName = static::pixeldrainFileName($video->storage_folder);

        return $this->proxyPixeldrainFile(
            $video->storage_folder,
            $fileName ? static::mimeForPath($fileName) : 'video/mp4'
        );
    }

    /**
     * Resolve the original filename of a Pixeldrain file via its API and cache
     * it, so stream responses can advertise the correct container/mime type.
     */
    protected static function pixeldrainFileName(string $fileId): ?string
    {
        $cacheKey = 'pd_file_name_' . $fileId;
        $cached = Cache::store('file')->get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $info = app(\App\Services\PixeldrainClient::class)->getFileInfo($fileId);
            $name = $info['name'] ?? null;

            if (is_string($name) && $name !== '') {
                Cache::store('file')->put($cacheKey, $name, now()->addDay());
                return $name;
            }
        } catch (\Throwable $e) {
            Log::warning('[PIXELDRAIN-STREAM] getFileInfo failed for ' . $fileId . ': ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Proxy a file stored on Pixeldrain through our server.
     *
     * Requests are proxied (the browser never talks to pixeldrain directly,
     * which sidesteps free-tier hotlink protection and ISP blocks) and upstream
     * Range requests are forwarded so seeking works.
     */
    protected function proxyPixeldrainFile(string $fileId, string $contentType): StreamedResponse
    {
        $client = app(\App\Services\PixeldrainClient::class);
        $url = $client->fileUrl($fileId);

        $headers = "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n" .
                   "Accept: */*\r\n";

        $authHeader = $client->authHeader();
        if ($authHeader) {
            $headers .= $authHeader . "\r\n";
        }

        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => $headers,
                'follow_location' => 1,
                'max_redirects' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ];

        $clientRange = request()->header('Range');
        if ($clientRange) {
            $opts['http']['header'] .= "Range: " . $clientRange . "\r\n";
        }

        $stream = @fopen($url, 'rb', false, stream_context_create($opts));

        if (! $stream) {
            $err = error_get_last();
            Log::warning('[PIXELDRAIN-STREAM] Failed to open upstream stream for file ' . $fileId . '. URL: ' . $url . '. Error: ' . ($err['message'] ?? 'unknown'));
            abort(502, 'Stream unavailable. Please try again later.');
        }

        $meta = static::parseStreamMeta($stream, $contentType);

        return response()->stream(function () use ($stream) {
            @ini_set('max_execution_time', '0');
            @ini_set('default_socket_timeout', '0');
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', 1);
            }
            @ini_set('zlib.output_compression', 'Off');
            @ob_implicit_flush(true);

            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            fpassthru($stream);
            fclose($stream);
        }, $meta['status'], $meta['headers']);
    }

    /**
     * Stream a pre-transcoded quality variant (720p/480p H.264) of a
     * Pixeldrain-hosted video. The variant already carries the default audio
     * track, so this plays the selected quality with the default language.
     */
    public function streamPixeldrainVariant(Video $video, \App\Models\VideoQualityVariant $variant)
    {
        if ($video->storage_provider !== 'pixeldrain' || $variant->video_id !== $video->id || ! $variant->file_path) {
            abort(404);
        }

        return $this->proxyPixeldrainFile($variant->file_path, 'video/mp4');
    }

    /**
     * Proxy a subtitle (.vtt) file stored on Pixeldrain through our server.
     * Subtitle rows created by the media processor use a `pixeldrain://<id>`
     * file_path; the player loads this route as the <track> src.
     */
    public function streamPixeldrainSubtitle(Video $video, \App\Models\Subtitle $subtitle)
    {
        if ($video->storage_provider !== 'pixeldrain' || $subtitle->video_id !== $video->id) {
            abort(404);
        }

        $fileId = $subtitle->file_path;
        if (! str_starts_with($fileId, 'pixeldrain://')) {
            abort(404);
        }

        return $this->proxyPixeldrainFile(substr($fileId, strlen('pixeldrain://')), 'text/vtt; charset=utf-8');
    }

    /**
     * Re-mux a Pixeldrain-hosted video with a chosen split audio track and
     * stream the result as fragmented MP4.
     *
     * The video is fetched from Pixeldrain, the selected audio track from
     * Pixeldrain, and ffmpeg muxes them together (video + audio stream are
     * both copied — no re-encode, so it is fast). Used by the audio switcher
     * for multi-audio Pixeldrain videos; the default track plays directly via
     * streamPixeldrain() without a re-mux.
     */
    public function streamPixeldrainAudio(Video $video, string $audioId)
    {
        if ($video->storage_provider !== 'pixeldrain' || ! $video->storage_folder) {
            abort(404);
        }

        $track = \App\Models\VideoAudioTrack::where('video_id', $video->id)
            ->where('storage', 'pixeldrain')
            ->where('file_path', $audioId)
            ->first();

        if (! $track) {
            abort(404);
        }

        return $this->streamPixeldrainRemux($video, $video->storage_folder, $audioId);
    }

    /**
     * Re-mux a pre-transcoded quality variant with a chosen split audio track.
     * Used when the user picks a non-default language while a 720p/480p variant
     * is active (the variant itself only carries the default audio).
     */
    public function streamPixeldrainVariantAudio(Video $video, string $variantId, string $audioId)
    {
        if ($video->storage_provider !== 'pixeldrain' || ! $video->storage_folder) {
            abort(404);
        }

        $variant = \App\Models\VideoQualityVariant::where('video_id', $video->id)
            ->where('storage', 'pixeldrain')
            ->where('file_path', $variantId)
            ->first();

        $track = \App\Models\VideoAudioTrack::where('video_id', $video->id)
            ->where('storage', 'pixeldrain')
            ->where('file_path', $audioId)
            ->first();

        if (! $variant || ! $track) {
            abort(404);
        }

        return $this->streamPixeldrainRemux($video, $variant->file_path, $audioId);
    }

    /**
     * Re-mux a video file (original or variant) stored on Pixeldrain with a
     * chosen split audio track and stream the result as fragmented MP4.
     *
     * Both inputs are fetched from Pixeldrain and both streams are copied (no
     * re-encode, so it is fast). Used by the audio/quality switcher for
     * multi-audio Pixeldrain videos; default audio plays directly via
     * streamPixeldrain()/streamPixeldrainVariant() without a re-mux.
     */
    protected function streamPixeldrainRemux(Video $video, string $videoFileId, string $audioId): StreamedResponse
    {
        $ffmpeg = config('ffmpeg.ffmpeg');
        if (! $ffmpeg) {
            Log::error('[PIXELDRAIN-AUDIO] FFmpeg not available for video ' . $video->id);
            abort(502, 'Transcoder unavailable.');
        }

        $client = app(\App\Services\PixeldrainClient::class);
        $videoUrl = $client->fileUrl($videoFileId);
        $audioUrl = $client->fileUrl($audioId);

        $authHeaders = '';
        if ($auth = $client->authHeader()) {
            $authHeaders = $auth . "\r\n";
        }

        return response()->stream(function () use ($video, $ffmpeg, $videoUrl, $audioUrl, $authHeaders) {
            @ini_set('zlib.output_compression', 'Off');
            @ini_set('max_execution_time', '0');
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', 1);
            }
            @ob_implicit_flush(true);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $args = [
                $ffmpeg,
                '-y', '-nostdin', '-loglevel', 'error',
                '-user_agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                '-headers', $authHeaders,
                '-tls_verify', '0',
                '-i', $videoUrl,
                '-headers', $authHeaders,
                '-tls_verify', '0',
                '-i', $audioUrl,
                '-map', '0:v:0',
                '-map', '1:a:0',
                '-c:v', 'copy',
                '-c:a', 'copy',
                '-avoid_negative_ts', 'make_zero',
                '-max_muxing_queue_size', '1024',
                '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
                '-f', 'mp4',
                'pipe:1',
            ];

            $process = proc_open($args, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);

            if (! is_resource($process)) {
                echo 'Stream unavailable.';
                return;
            }

            fclose($pipes[0]);

            while (! feof($pipes[1])) {
                $chunk = fread($pipes[1], 65536);
                if ($chunk !== false && $chunk !== '') {
                    echo $chunk;
                    flush();
                }
            }
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                Log::error('[PIXELDRAIN-AUDIO] ffmpeg exited with code ' . $exitCode . ' for video ' . $video->id, [
                    'video_file' => $videoFileId,
                    'audio_id' => $audioId,
                    'stderr' => trim((string) $stderr),
                ]);
            }
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Accept-Ranges' => 'none',
        ]);
    }

    /**
     * Proxy the original 1080p file from TeraBox through our server.
     *
     * Note: TeraBox throttles free-account dlinks hard, so this mode cannot
     * stream 1080p smoothly — it is kept as a manual last-resort. The dlink is
     * cached for a couple of minutes (and re-resolved once if it is consumed)
     * so the browser's sequential range requests don't each pay the full
     * session-verification latency.
     */
    public function streamDirect(Video $video)
    {
        if ($video->video_url !== 'terabox-remote' || ! $video->storage_folder) {
            abort(404);
        }

        $contentType = static::mimeForPath($video->storage_folder);
        $terabox = app(TeraBoxClient::class);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                $dlink = static::resolveDirectLink($video, $terabox);

                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'header' => "User-Agent: " . config('terabox.user_agent') . "\r\n" .
                                    "Referer: https://www.1024terabox.com/\r\n",
                    ],
                ];

                $clientRange = request()->header('Range');
                if ($clientRange) {
                    $opts['http']['header'] .= "Range: " . $clientRange . "\r\n";
                }

                $stream = @fopen($dlink, 'rb', false, stream_context_create($opts));

                if (!$stream) {
                    throw new \RuntimeException('Failed to open remote stream.');
                }

                $meta = static::parseStreamMeta($stream, $contentType);

                return response()->stream(function () use ($stream) {
                    // Disable limits and compression
                    @ini_set('max_execution_time', '0');
                    @ini_set('default_socket_timeout', '0');
                    if (function_exists('apache_setenv')) {
                        @apache_setenv('no-gzip', 1);
                    }
                    @ini_set('zlib.output_compression', 'Off');
                    @ob_implicit_flush(true);

                    while (ob_get_level() > 0) {
                        ob_end_clean();
                    }

                    fpassthru($stream);
                    fclose($stream);
                }, $meta['status'], $meta['headers']);
            } catch (\Throwable $e) {
                // The cached single-use dlink may have been consumed by another
                // connection — drop it and let the loop resolve a fresh one.
                Log::warning('[STREAM-DIRECT] Attempt ' . ($attempt + 1) . ' failed for video ' . $video->id . ': ' . $e->getMessage());
                Cache::store('file')->forget('terabox_dlink_' . $video->id);
            }
        }

        Log::error('[STREAM-DIRECT] Proxy failed for video ' . $video->id);
        abort(502, 'Stream proxy failed.');
    }

    /**
     * Stream the video transcoded to 720p on the fly.
     *
     * Like streamDirect(), the source is the TeraBox download link (so it is
     * still throttled and "slow"), but the bytes are piped through FFmpeg and
     * re-encoded to 720p before reaching the player. This is the only way to
     * get true 720p on a free TeraBox account, whose streaming endpoint caps
     * transcoded HLS at 480p.
     */
    public function stream720(Video $video)
    {
        if ($video->video_url !== 'terabox-remote' || ! $video->storage_folder) {
            abort(404);
        }

        $ffmpeg = config('ffmpeg.ffmpeg');
        if (! $ffmpeg || (str_contains($ffmpeg, DIRECTORY_SEPARATOR) && ! file_exists($ffmpeg))) {
            Log::error('[STREAM-720] FFmpeg not available for video ' . $video->id);
            abort(502, 'Transcoder unavailable.');
        }

        return response()->stream(function () use ($video, $ffmpeg) {
            @ini_set('zlib.output_compression', 'Off');
            @ini_set('max_execution_time', '0');
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', 1);
            }
            @ob_implicit_flush(true);
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $terabox = app(TeraBoxClient::class);
            $dlink = null;

            for ($attempt = 0; $attempt < 2; $attempt++) {
                try {
                    $dlink = static::resolveDirectLink($video, $terabox);
                    break;
                } catch (\Throwable $e) {
                    Log::warning('[STREAM-720] dlink resolve attempt ' . ($attempt + 1) . ' failed for video ' . $video->id . ': ' . $e->getMessage());
                    Cache::store('file')->forget('terabox_dlink_' . $video->id);
                }
            }

            if (! $dlink) {
                echo 'Stream unavailable.';
                return;
            }

            $args = [
                $ffmpeg,
                '-nostdin',
                '-loglevel', 'error',
                '-reconnect', '1',
                '-reconnect_streamed', '1',
                '-reconnect_delay_max', '5',
                '-user_agent', config('terabox.user_agent'),
                '-headers', "Referer: https://www.1024terabox.com/\r\n",
                '-i', $dlink,
                '-vf', 'scale=-2:min(720\,ih)',
                '-c:v', 'libx264',
                '-preset', 'veryfast',
                '-crf', '26',
                '-c:a', 'aac',
                '-b:a', '128k',
                '-ac', '2',
                '-movflags', 'frag_keyframe+empty_moov+default_base_moof',
                '-f', 'mp4',
                'pipe:1',
            ];

            $process = proc_open($args, [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ], $pipes);

            if (! is_resource($process)) {
                echo 'Stream unavailable.';
                return;
            }

            // ffmpeg needs no stdin.
            fclose($pipes[0]);

            $exitCode = null;
            while (! feof($pipes[1])) {
                $chunk = fread($pipes[1], 65536);
                if ($chunk !== false && $chunk !== '') {
                    echo $chunk;
                    flush();
                }
            }
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                Log::error('[STREAM-720] ffmpeg exited with code ' . $exitCode . ' for video ' . $video->id, ['stderr' => trim($stderr)]);
            }
        }, 200, [
            'Content-Type' => 'video/mp4',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Accept-Ranges' => 'none',
        ]);
    }

    /**
     * Resolve a direct download link for a TeraBox video, caching it briefly.
     */
    protected static function resolveDirectLink(Video $video, TeraBoxClient $terabox): string
    {
        $cacheKey = 'terabox_dlink_' . $video->id;
        $cached = Cache::store('file')->get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        if (str_starts_with($video->storage_folder, 'http://') || str_starts_with($video->storage_folder, 'https://')) {
            $dlink = $terabox->getLinkFromShare($video->storage_folder);
        } else {
            $dlink = $terabox->getDirectLink($video->storage_folder);
        }

        Cache::store('file')->put($cacheKey, $dlink, now()->addMinutes(2));

        return $dlink;
    }

    protected static function mimeForPath(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mimeMap = [
            'mp4'  => 'video/mp4',
            'mkv'  => 'video/x-matroska',
            'webm' => 'video/webm',
            'avi'  => 'video/x-msvideo',
            'mov'  => 'video/quicktime',
        ];

        return $mimeMap[$ext] ?? 'video/mp4';
    }

    /**
     * Extract the upstream status code + range metadata so the browser can
     * seek through the proxied stream.
     */
    protected static function parseStreamMeta($stream, string $contentType): array
    {
        $metaData = stream_get_meta_data($stream);
        $wrapperData = $metaData['wrapper_data'] ?? [];

        $contentLength = null;
        $contentRange = null;
        $statusCode = 200;

        foreach ($wrapperData as $headerLine) {
            if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/i', $headerLine, $matches)) {
                $statusCode = (int) $matches[1];
            } elseif (preg_match('/^Content-Length:\s*(\d+)/i', $headerLine, $matches)) {
                $contentLength = $matches[1];
            } elseif (preg_match('/^Content-Range:\s*(.+)/i', $headerLine, $matches)) {
                $contentRange = $matches[1];
            }
        }

        $headers = [
            'Content-Type' => $contentType,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            // Tell nginx to pass proxied bytes through immediately instead of
            // buffering the whole upstream body, so seeks start instantly.
            'X-Accel-Buffering' => 'no',
        ];

        if ($contentLength !== null) {
            $headers['Content-Length'] = $contentLength;
        }
        if ($contentRange !== null) {
            $headers['Content-Range'] = $contentRange;
        }

        return [
            'status' => $statusCode,
            'headers' => $headers,
        ];
    }

    /**
     * Diagnostic endpoint to test TeraBox connectivity from the server.
     */
    public function teraboxTest()
    {
        $results = [];

        // 1. Show what credentials we have
        $dbNdus = \App\Models\Setting::get('terabox_ndus');
        $configNdus = config('terabox.ndus');
        $dbEmail = \App\Models\Setting::get('terabox_email');
        $configEmail = config('terabox.email');
        $dbPassword = \App\Models\Setting::get('terabox_password');
        $configPassword = config('terabox.password');
        $webHost = config('terabox.web_host', 'https://www.terabox.com');

        $results['credentials'] = [
            'db_ndus' => $dbNdus ? substr($dbNdus, 0, 10) . '... (' . strlen($dbNdus) . ' chars)' : 'NOT SET',
            'config_ndus' => $configNdus ? substr($configNdus, 0, 10) . '... (' . strlen($configNdus) . ' chars)' : 'NOT SET',
            'db_email' => $dbEmail ? substr($dbEmail, 0, 5) . '...' : 'NOT SET',
            'config_email' => $configEmail ? substr($configEmail, 0, 5) . '...' : 'NOT SET',
            'has_db_password' => !empty($dbPassword),
            'has_config_password' => !empty($configPassword),
            'web_host' => $webHost,
        ];

        // 2. Test ensureAuthenticated
        $terabox = app(TeraBoxClient::class);
        try {
            $terabox->ensureAuthenticated();
            $results['auth'] = ['status' => 'SUCCESS', 'authed' => $terabox->isAuthed()];
        } catch (\Throwable $e) {
            $results['auth'] = ['status' => 'FAILED', 'error' => $e->getMessage()];
        }

        // 3. Test getDirectLink for video 3
        $video = \App\Models\Video::find(3);
        if ($video) {
            try {
                $terabox2 = app(TeraBoxClient::class);
                $dlink = $terabox2->getDirectLink($video->storage_folder);
                $results['direct_link'] = ['status' => 'SUCCESS', 'dlink' => substr($dlink, 0, 80) . '...'];
            } catch (\Throwable $e) {
                $results['direct_link'] = ['status' => 'FAILED', 'error' => $e->getMessage(), 'path' => $video->storage_folder];
            }
        }

        // 4. Test raw HTTP to TeraBox main page
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $resp = $client->request('GET', 'https://www.1024terabox.com/', [
                'headers' => ['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'],
            ]);
            $results['terabox_reachable'] = [
                'status' => 'OK',
                'http_code' => $resp->getStatusCode(),
                'body_length' => strlen((string) $resp->getBody()),
            ];
        } catch (\Throwable $e) {
            $results['terabox_reachable'] = ['status' => 'FAILED', 'error' => $e->getMessage()];
        }

        // 5. Test share/list API (public, no cookies)
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 10]);
            $resp = $client->request('GET', 'https://www.1024terabox.com/share/list?app_id=250528&web=1&channel=dubox&clienttype=0&page=1&num=20&by=name&order=asc&surl=test123&root=1', [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Referer' => 'https://www.1024terabox.com/',
                ],
            ]);
            $shareJson = json_decode((string) $resp->getBody(), true);
            $results['share_api'] = [
                'status' => 'API_REACHABLE',
                'http_code' => $resp->getStatusCode(),
                'api_errno' => $shareJson['errno'] ?? 'unknown',
                'note' => 'errno 2 = invalid surl (expected for test), API is working',
            ];
        } catch (\Throwable $e) {
            $results['share_api'] = ['status' => 'FAILED', 'error' => $e->getMessage()];
        }

        // 6. Server IP
        try {
            $client = new \GuzzleHttp\Client(['timeout' => 5]);
            $resp = $client->request('GET', 'https://api.ipify.org?format=json');
            $results['server_ip'] = json_decode((string) $resp->getBody(), true);
        } catch (\Throwable $e) {
            $results['server_ip'] = ['error' => $e->getMessage()];
        }

        return response()->json($results, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
