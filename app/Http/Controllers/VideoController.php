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
        $video = Video::with('category')->where('slug', $slug)->firstOrFail();

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

        return view('frontend.video.show', compact('video', 'relatedVideos', 'recentlyWatched', 'streamUrl', 'inMyList'));
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

        $rewritten = Cache::remember('terabox_hls_' . $video->id, now()->addMinutes(30), function () use ($video) {
            try {
                $terabox = app(TeraBoxClient::class);
                $playlist = $terabox->getHlsPlaylist($video->storage_folder, 'M3U8_AUTO_480');
            } catch (\Throwable $e) {
                Log::warning('[TERABOX-HLS] Failed to fetch playlist.', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage(),
                ]);

                abort(502, 'Stream unavailable. Please try again later.');
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

        try {
            $terabox = app(TeraBoxClient::class);
            $playlist = $terabox->getHlsPlaylist($video->storage_folder, 'M3U8_AUTO_480');

            Cache::put('terabox_hls_' . $video->id, static::rewritePlaylist($video, $playlist), now()->addMinutes(30));
            static::warmFirstSegment($video, $terabox, $playlist);
        } catch (\Throwable $e) {
            Log::debug('[TERABOX-HLS] Pre-warm skipped for video ' . $video->id . ': ' . $e->getMessage());
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
     * Redirect directly to TeraBox's high-quality dlink for 1080p streaming.
     */
    public function streamDirect(Video $video)
    {
        if ($video->video_url !== 'terabox-remote' || ! $video->storage_folder) {
            abort(404);
        }

        try {
            $terabox = app(TeraBoxClient::class);
            if (str_starts_with($video->storage_folder, 'http://') || str_starts_with($video->storage_folder, 'https://')) {
                $dlink = $terabox->getLinkFromShare($video->storage_folder);
            } else {
                $dlink = $terabox->getDirectLink($video->storage_folder);
            }
            return redirect()->away($dlink);
        } catch (\Throwable $e) {
            $dbNdus = \App\Models\Setting::get('terabox_ndus');
            $configNdus = config('terabox.ndus');
            Log::error('[STREAM-DIRECT] Failed: ' . $e->getMessage());
            abort(502, 'Failed: ' . $e->getMessage() . ' (DB NDUS: ' . substr($dbNdus, 0, 10) . '..., Config NDUS: ' . substr($configNdus, 0, 10) . '...)');
        }
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
