<?php

namespace App\Services;

use App\Models\ActivityLog;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use RuntimeException;

class TeraBoxClient
{
    public const APP_ID = 250528;
    public const APP_CHANNEL = 'dubox';
    public const DOMAIN = 'terabox.com';

    protected Client $http;
    protected CookieJar $cookies;
    protected string $whost;
    protected string $uhost;
    protected string $jsToken = '';
    protected string $csrf = '';
    protected string $pcftoken = '';
    protected string $bdstoken = '';
    protected bool $authed = false;
    protected array $lastHeaders = [];
    protected $progressCallback = null;
    protected int $progressTotal = 0;
    protected int $progressUploaded = 0;

    public function __construct()
    {
        $this->whost = rtrim(config('terabox.web_host', 'https://www.terabox.com'), '/');
        $this->uhost = 'https://c-all.' . self::DOMAIN;
        $this->cookies = new CookieJar();
        $this->http = new Client([
            'timeout' => 120,
            'allow_redirects' => ['max' => 5, 'strict' => true, 'referer' => true],
            'cookies' => $this->cookies,
        ]);
    }

    protected function ua(): string
    {
        return config('terabox.user_agent');
    }

    /**
     * Register a callback fired as upload progress changes.
     * Signature: fn(int $bytesUploaded, int $totalBytes).
     */
    public function onProgress(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    /**
     * Cache flag telling the admin UI that the stored NDUS cookie is invalid.
     */
    public static function sessionExpired(): bool
    {
        return (bool) Cache::get('terabox_session_expired');
    }

    protected function notifySessionExpired(): void
    {
        Cache::put('terabox_session_expired', true, now()->addDay());

        if (! Cache::get('terabox_expiry_logged')) {
            try {
                ActivityLog::create([
                    'user_id' => null,
                    'description' => 'TeraBox session cookie (NDUS) has expired. Update credentials in Admin → Settings.',
                    'ip_address' => Request::ip(),
                    'user_agent' => 'system',
                ]);
            } catch (\Throwable $e) {
                Log::warning('[TERABOX] Could not persist expiry notification: ' . $e->getMessage());
            }
            Cache::put('terabox_expiry_logged', true, now()->addHour());
        }
    }

    protected function emitProgress(): void
    {
        if ($this->progressCallback && $this->progressTotal > 0) {
            call_user_func($this->progressCallback, $this->progressUploaded, $this->progressTotal);
        }
    }

    public function isAuthed(): bool
    {
        return $this->authed;
    }

    /**
     * Ensure we have a valid session (ndus) + jsToken before any API call.
     */
    public function ensureAuthenticated(): void
    {
        if ($this->authed) {
            return;
        }

        $this->applySessionCookie();

        // When using a stored NDUS cookie, verify it is still valid (this is
        // what also detects expiry and surfaces the admin notification).
        $ndus = \App\Models\Setting::get('terabox_ndus') ?: config('terabox.ndus');
        if ($ndus) {
            $this->verifySession();
        }
    }

    /**
     * Attach the stored NDUS cookie (or perform a full password login) so a
     * session is available for API calls.
     *
     * Unlike ensureAuthenticated(), this does NOT hit the network to verify
     * the session. Endpoints that only need the cookie present (e.g. the HLS
     * /api/streaming endpoint) can use this to avoid two extra round-trips,
     * which is what makes playlist startup fast.
     */
    protected function applySessionCookie(): void
    {
        if ($this->authed) {
            return;
        }

        $ndus = \App\Models\Setting::get('terabox_ndus') ?: config('terabox.ndus');
        if ($ndus) {
            $this->setSessionCookie('ndus', $ndus);
            return;
        }

        $email = \App\Models\Setting::get('terabox_email') ?: config('terabox.email');
        $password = \App\Models\Setting::get('terabox_password') ?: config('terabox.password');
        if ($email && $password) {
            $this->login($email, $password);
            return;
        }

        throw new RuntimeException('TeraBox is not configured. Set TERABOX_NDUS or TERABOX_EMAIL/TERABOX_PASSWORD in .env');
    }

    protected function setCookie(string $name, string $value, ?string $domain = null): void
    {
        $this->cookies->setCookie(new \GuzzleHttp\Cookie\SetCookie([
            'Name' => $name,
            'Value' => $value,
            'Domain' => $domain ?? self::DOMAIN,
            'Path' => '/',
        ]));
    }

    /**
     * Set the ndus cookie on every plausible TeraBox domain so it is always
     * sent regardless of which regional host (www/dm/e/c-*.terabox.com or
     * the *.1024terabox.com equivalent) the client is redirected to.
     */
    protected function setSessionCookie(string $name, string $value): void
    {
        $this->setCookie($name, $value, self::DOMAIN);
        $this->setCookie($name, $value, '.1024terabox.com');

        // If web host points at a 1024terabox.com node, use its base domain too.
        $host = parse_url($this->whost, PHP_URL_HOST);
        if (is_string($host) && str_ends_with($host, '1024terabox.com')) {
            $this->setCookie($name, $value, '.1024terabox.com');
        } elseif (is_string($host) && str_ends_with($host, 'terabox.com')) {
            $this->setCookie($name, $value, '.terabox.com');
        }
    }

    protected function getCookie(string $name): ?string
    {
        $cookie = $this->cookies->getCookieByName($name);
        return $cookie ? $cookie->getValue() : null;
    }

    /**
     * GET a URL and return decoded JSON + set-cookie headers.
     */
    protected function getJson(string $url, array $options = []): array
    {
        return $this->request('GET', $url, $options);
    }

    protected function postForm(string $url, array $form, array $options = []): array
    {
        return $this->request('POST', $url, array_merge([
            'form_params' => $form,
        ], $options));
    }

    protected function request(string $method, string $url, array $options = []): array
    {
        $options['headers'] = array_merge([
            'User-Agent' => $this->ua(),
            'Referer' => $this->whost,
            'Accept' => 'application/json, text/plain, */*',
        ], $options['headers'] ?? []);

        Log::debug('[TERABOX-REQ] ' . $method . ' ' . $url);

        $response = $this->http->request($method, $url, $options);

        $this->lastHeaders = $response->getHeaders();
        $body = (string) $response->getBody();
        $json = json_decode($body, true);
        if (! is_array($json)) {
            $json = [];
        }

        return $json;
    }

    /**
     * Fetch whost/main page, parse templateData + jsToken, persist cookies.
     */
    protected function updateAppData(string $path = '/main'): array
    {
        $response = $this->http->request('GET', $this->whost . $path, [
            'headers' => ['User-Agent' => $this->ua()],
            'allow_redirects' => ['max' => 5, 'strict' => true, 'referer' => true],
        ]);

        $html = (string) $response->getBody();
        $tdata = [];

        $startMarker = '<script>var templateData = ';
        $markerPos = strpos($html, $startMarker);
        if ($markerPos !== false) {
            $from = $markerPos + strlen($startMarker);
            $end = strpos($html, '</script>', $from);
            if ($end !== false) {
                $raw = rtrim(substr($html, $from, $end - $from), '; ');
                try {
                    $tdata = json_decode($raw, true) ?: [];
                } catch (\Throwable $e) {
                    $tdata = [];
                }
            }
        }

        // Follow the account's regional host if the page tells us it changed.
        $newOrigin = $tdata['newDomain']['origin'] ?? null;
        if (is_string($newOrigin) && $newOrigin && $newOrigin !== $this->whost) {
            $this->whost = rtrim($newOrigin, '/');
        }

        $jsToken = $this->extractJsToken($html);

        if (empty($jsToken) && isset($tdata['jsToken'])) {
            $jsToken = $tdata['jsToken'];
            if (preg_match('/%28%22(.*)%22%29/', $jsToken, $m2)) {
                $jsToken = $m2[1];
            }
        }

        $this->jsToken = $jsToken;
        $this->csrf = $tdata['csrf'] ?? $this->csrf;
        $this->pcftoken = $tdata['pcftoken'] ?? $this->pcftoken;
        $this->bdstoken = $tdata['bdstoken'] ?? $this->bdstoken;

        return $tdata;
    }

    protected function extractJsToken(string $html): string
    {
        $start = '`function%20fn%28a%29%7Bwindow.jsToken%20%3D%20a%7D%3Bfn%28%22';
        $end = '%22%29`';
        $pos = strpos($html, $start);
        if ($pos !== false) {
            $from = $pos + strlen($start);
            $to = strpos($html, $end, $from);
            if ($to !== false) {
                return substr($html, $from, $to - $from);
            }
        }

        if (preg_match('/window\.jsToken%20%3D%20a%7D%3Bfn%28%22(.*?)%22%29/', $html, $m)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Verify the current session (ndus) is valid.
     */
    protected function verifySession(): void
    {
        $this->updateAppData();
        $this->checkLogin();
        $this->authed = true;
    }

    protected function checkLogin(): void
    {
        $json = $this->getJson($this->whost . '/api/check/login');

        $region = $this->lastHeader('region-domain-prefix');
        if ($region) {
            $this->whost = 'https://' . $region . '.' . self::DOMAIN;
            $this->checkLogin();
            return;
        }

        if (($json['errno'] ?? 0) !== 0) {
            $this->notifySessionExpired();
            throw new RuntimeException('TeraBox session invalid (errno ' . ($json['errno'] ?? '?') . '). Re-auth required.');
        }
    }

    protected function lastHeader(string $name): ?string
    {
        foreach ($this->lastHeaders as $key => $values) {
            if (strcasecmp($key, $name) === 0) {
                return is_array($values) ? ($values[0] ?? null) : $values;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------
     * Login via email + password (passport flow)
     * ------------------------------------------------------------------ */

    public function login(string $email, string $password): void
    {
        Log::info('[TERABOX-LOGIN] Starting password login for ' . $email);

        // Step 1: get pcftoken + jsToken from the login page
        $this->updateAppData('/wap/outlogin/login');

        if (empty($this->pcftoken)) {
            throw new RuntimeException('Could not obtain pcftoken from TeraBox login page.');
        }

        // Step 2: pre-login
        $pre = $this->postForm($this->whost . '/passport/prelogin', [
            'client' => 'web',
            'pass_version' => '2.8',
            'clientfrom' => 'h5',
            'pcftoken' => $this->pcftoken,
            'email' => $email,
        ]);

        $preCode = $pre['code'] ?? -1;
        $preData = $pre['data'] ?? [];
        if ($preCode !== 0 || empty($preData)) {
            throw new RuntimeException('TeraBox pre-login failed (code ' . $preCode . '). ' .
                ($pre['msg'] ?? '') . ' This is often a captcha/risk-control block on automated logins.');
        }

        // Step 3: get RSA public key
        $keyJson = $this->getJson($this->whost . '/passport/getpubkey');
        $keyData = $keyJson['data'] ?? [];
        if (($keyJson['code'] ?? -1) !== 0 || empty($keyData['pp1']) || empty($keyData['pp2'])) {
            throw new RuntimeException('Could not obtain TeraBox public key.');
        }

        $pubkey = $this->decryptAES($keyData['pp1'], $keyData['pp2']);

        // Step 4: encrypt password
        $encpwd = $this->changeBase64Type($this->encryptRSA($password, $pubkey, 2), 1);

        // Step 5: browserid cookie
        $browserid = $this->getCookie('browserid') ?? '';

        // Step 6: prand = sha1(web-seval-encpwd-email-browserid-random)
        $prand = sha1(implode('-', [
            'web',
            $preData['seval'],
            $encpwd,
            $email,
            $browserid,
            $preData['random'],
        ]));

        // Step 7: login
        $login = $this->postForm($this->whost . '/passport/login', [
            'client' => 'web',
            'pass_version' => '2.8',
            'clientfrom' => 'h5',
            'pcftoken' => $this->pcftoken,
            'prand' => $prand,
            'email' => $email,
            'pwd' => $encpwd,
            'seval' => $preData['seval'],
            'random' => $preData['random'],
            'timestamp' => $preData['timestamp'],
        ]);

        if (($login['code'] ?? -1) !== 0) {
            throw new RuntimeException('TeraBox login failed (code ' . ($login['code'] ?? '?') . '): ' .
                ($login['msg'] ?? '') . ' — likely a captcha/risk-control block.');
        }

        $ndus = $this->getCookie('ndus');
        if (! $ndus) {
            throw new RuntimeException('Login succeeded but no ndus cookie was issued.');
        }

        $this->authed = true;
        Log::info('[TERABOX-LOGIN] Login successful. ndus obtained.');
        Cache::forget('terabox_session_expired');
        Cache::forget('terabox_expiry_logged');
        $this->updateAppData();
    }

    /* ------------------------------------------------------------------
     * Upload API
     * ------------------------------------------------------------------ */

    protected function buildApiQuery(array $extra = []): array
    {
        return array_merge([
            'app_id' => self::APP_ID,
            'web' => '1',
            'channel' => self::APP_CHANNEL,
            'clienttype' => '0',
        ], $extra);
    }

    public function getUploadHost(): string
    {
        $json = $this->getJson($this->whost . '/rest/2.0/pcs/file?method=locateupload');
        if (isset($json['host']) && $json['host']) {
            $this->uhost = 'https://' . $json['host'];
        }
        return $this->uhost;
    }

    public function createDir(string $dir): void
    {
        $json = $this->postForm(
            $this->whost . '/api/create?a=commit',
            [
                'path' => $dir,
                'isdir' => 1,
                'block_list' => '[]',
            ]
        );

        $errno = $json['errno'] ?? 0;

        // -6 => endpoint host changed; switch to the returned url-domain-prefix and retry.
        if ($errno === -6) {
            $prefix = $this->lastHeader('url-domain-prefix');
            if ($prefix) {
                $this->whost = 'https://' . $prefix . '.' . self::DOMAIN;
                $this->createDir($dir);
                return;
            }
        }

        if ($errno !== 0 && $errno !== -8) { // -8 = already exists
            throw new RuntimeException('TeraBox createDir failed: ' . $errno);
        }
    }

    /**
     * Upload a local file to the remote directory.
     * Returns the remote path on success.
     */
    public function uploadFile(string $localPath, string $remoteDir, string $filename): string
    {
        if (! is_file($localPath)) {
            throw new RuntimeException('Local file not found: ' . $localPath);
        }

        $size = filesize($localPath);
        if ($size <= 0) {
            throw new RuntimeException('Cannot upload empty file.');
        }

        $this->progressTotal = $size;
        $this->progressUploaded = 0;
        $this->emitProgress();

        $this->ensureAuthenticated();

        $remoteDir = rtrim($remoteDir, '/');
        $remotePath = $remoteDir . '/' . $filename;

        $hash = $this->hashFile($localPath);

        // 1) Try rapid upload (instant if server already has the file)
        $rapid = $this->rapidUpload($remotePath, $size, $hash);
        if (($rapid['errno'] ?? 1) === 0) {
            $this->progressUploaded = $size;
            $this->emitProgress();
            Log::info('[TERABOX-UPLOAD] Rapid upload succeeded (server-side dedupe).');
            return $remotePath;
        }

        // 2) Precreate
        $this->getUploadHost();
        $pre = $this->precreate($remotePath, $size, $hash);
        if (($pre['errno'] ?? 1) !== 0) {
            throw new RuntimeException('TeraBox precreate failed: errno ' . ($pre['errno'] ?? '?'));
        }

        if (($pre['return_type'] ?? 0) === 2) {
            Log::info('[TERABOX-UPLOAD] Precreate returned instant-upload (return_type=2).');
            return $remotePath;
        }

        $uploadId = $pre['uploadid'] ?? '';
        // Server's block_list = indices that STILL need uploading.
        $neededBlocks = isset($pre['block_list']) && is_array($pre['block_list'])
            ? array_map('intval', $pre['block_list'])
            : [];

        // 3) Upload each chunk
        $chunkSize = $this->chunkSize($size);
        $chunkHashes = $hash['chunks'];
        $total = count($chunkHashes);

        for ($partseq = 0; $partseq < $total; $partseq++) {
            if (! in_array($partseq, $neededBlocks, true)) {
                continue;
            }
            $this->uploadChunk($localPath, $remotePath, $uploadId, $partseq, $chunkSize, $size);
            $this->progressUploaded = min($size, ($partseq + 1) * $chunkSize);
            $this->emitProgress();
        }

        // 4) Finalize
        $create = $this->createFile($remotePath, $size, $uploadId, $hash);
        if (($create['errno'] ?? 1) !== 0) {
            throw new RuntimeException('TeraBox createFile failed: errno ' . ($create['errno'] ?? '?'));
        }

        Log::info('[TERABOX-UPLOAD] Upload complete.', ['path' => $remotePath, 'size' => $size]);

        return $remotePath;
    }

    protected function rapidUpload(string $remotePath, int $size, array $hash): array
    {
        $form = [
            'path' => $remotePath,
            'content-length' => $size,
            'content-md5' => $hash['file'],
            'slice-md5' => $hash['slice'],
            'content-crc32' => $hash['crc32'],
            'block_list' => json_encode($hash['chunks']),
            'rtype' => 2,
            'mode' => 1,
        ];

        if ($size < 256 * 1024 || ! preg_match('/^[a-f0-9]{32}$/', $hash['file'])) {
            return ['errno' => 404];
        }

        try {
            return $this->postForm(
                $this->whost . '/api/rapidupload?' . http_build_query($this->buildApiQuery(['jsToken' => $this->jsToken])),
                $form
            );
        } catch (\Throwable $e) {
            Log::warning('[TERABOX-UPLOAD] Rapid upload failed, falling back to chunked upload.', ['error' => $e->getMessage()]);
            return ['errno' => 404];
        }
    }

    protected function precreate(string $remotePath, int $size, array $hash): array
    {
        $blockList = $hash['chunks'];
        if (empty($blockList)) {
            $blockList = ['5910a591dd8fc18c32a8f3df4fdc1761'];
            if ($size > 4 * 1024 * 1024) {
                $blockList[] = 'a5fc157d78e6ad1c7e114b056c92821e';
            }
        }

        $form = [
            'path' => $remotePath,
            'autoinit' => 1,
            'size' => $size,
            'file_limit_switch_v34' => 'true',
            'block_list' => json_encode($blockList),
            'rtype' => 2,
        ];

        if (preg_match('/^[a-f0-9]{32}$/', $hash['file'])) {
            $form['content-md5'] = $hash['file'];
            $form['slice-md5'] = $hash['slice'];
        }
        $form['content-crc32'] = $hash['crc32'];

        $url = $this->whost . '/api/precreate?' . http_build_query($this->buildApiQuery(['jsToken' => $this->jsToken]));
        $json = $this->postForm($url, $form);

        if (($json['errno'] ?? 0) === 4000023) {
            $this->updateAppData();
            return $this->precreate($remotePath, $size, $hash);
        }

        return $json;
    }

    protected function uploadChunk(string $localPath, string $remotePath, string $uploadId, int $partseq, int $chunkSize, int $size): void
    {
        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open local file for chunk read.');
        }
        fseek($handle, $partseq * $chunkSize);
        $data = fread($handle, $chunkSize);
        fclose($handle);

        $query = http_build_query([
            'method' => 'upload',
            'app_id' => self::APP_ID,
            'web' => '1',
            'channel' => self::APP_CHANNEL,
            'clienttype' => '0',
            'path' => $remotePath,
            'uploadid' => $uploadId,
            'partseq' => $partseq,
        ]);

        $url = $this->uhost . '/rest/2.0/pcs/superfile2?' . $query;

        $response = $this->http->request('POST', $url, [
            'headers' => ['User-Agent' => $this->ua()],
            'timeout' => 3600,
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => $data,
                    'filename' => 'blob',
                ],
            ],
        ]);

        $json = json_decode((string) $response->getBody(), true) ?: [];

        if (! empty($json['error_code'])) {
            throw new RuntimeException('Chunk upload failed (part ' . $partseq . '): ' . $json['error_code']);
        }
    }

    protected function createFile(string $remotePath, int $size, string $uploadId, array $hash): array
    {
        $form = [
            'path' => $remotePath,
            'size' => $size,
            'isdir' => 0,
            'content-md5' => $hash['file'],
            'slice-md5' => $hash['slice'],
            'content-crc32' => $hash['crc32'],
            'block_list' => json_encode($hash['chunks']),
            'uploadid' => $uploadId,
            'rtype' => 2,
        ];

        $url = $this->whost . '/api/create?a=commit';
        return $this->postForm($url, $form);
    }

    /**
     * Get a direct download / streaming link for a remote file.
     */
    public function getDirectLink(string $remotePath): string
    {
        $json = $this->postForm(
            $this->whost . '/api/filemetas',
            [
                'dlink' => 1,
                'origin' => 'dlna',
                'target' => json_encode([$remotePath]),
            ]
        );

        $info = $json['info'] ?? [];
        $dlink = $info[0]['dlink'] ?? '';
        if (! $dlink) {
            throw new RuntimeException('Could not obtain direct link for ' . $remotePath . ' (errno ' . ($json['errno'] ?? '?') . ')');
        }

        return $dlink;
    }

    /**
     * Get an HLS (m3u8) playlist for a remote video file.
     *
     * This is what TeraBox's own web player uses. Unlike dlinks (which are
     * single-use and reset after the first connection), the streaming endpoint
     * returns a fresh playlist of segment URLs on every call, each segment
     * being independently servable.
     */
    public function getHlsPlaylist(string $remotePath, string $type = 'M3U8_AUTO_480'): string
    {
        $this->ensureAuthenticated();

        $response = $this->http->request('POST', $this->whost . '/api/streaming', [
            'form_params' => [
                'path' => $remotePath,
                'type' => $type,
                'vip' => 2,
            ],
            'headers' => [
                'User-Agent' => $this->ua(),
                'Referer' => $this->whost,
            ],
            'timeout' => 120,
        ]);

        $playlist = (string) $response->getBody();

        if (! str_contains($playlist, '#EXTM3U')) {
            throw new RuntimeException('TeraBox streaming did not return an HLS playlist for ' . $remotePath);
        }

        return $playlist;
    }

    /**
     * Proxy an HLS segment: fetch it with the TeraBox Referer so the CDN
     * serves it, then stream the bytes back to the player.
     */
    public function fetchSegment(string $url): array
    {
        $headers = [
            'User-Agent' => $this->ua(),
            'Referer' => 'https://www.1024terabox.com/',
            'Accept' => '*/*',
        ];

        $response = $this->http->request('GET', $url, [
            'headers' => $headers,
            'timeout' => 120,
            'allow_redirects' => ['max' => 5, 'strict' => true, 'referer' => true],
        ]);

        return [
            'status' => $response->getStatusCode(),
            'type' => $response->getHeaderLine('Content-Type') ?: 'video/mp2t',
            'body' => (string) $response->getBody(),
        ];
    }

    /**
     * Delete a file (or directory) from the remote TeraBox drive.
     */
    public function deleteFile(string $remotePath): bool
    {
        $this->ensureAuthenticated();

        $url = $this->whost . '/api/filemanager?' . http_build_query($this->buildApiQuery([
            'jsToken' => $this->jsToken,
            'onnest' => 'fail',
            'opera' => 'delete',
        ]));

        $json = $this->postForm($url, [
            'filelist' => json_encode([$remotePath]),
        ]);

        if (($json['errno'] ?? 1) !== 0) {
            throw new RuntimeException('TeraBox delete failed for ' . $remotePath . ' (errno ' . ($json['errno'] ?? '?') . ')');
        }

        return true;
    }

    /* ------------------------------------------------------------------
     * Hashing & crypto helpers
     * ------------------------------------------------------------------ */

    protected function chunkSize(int $size): int
    {
        // Free accounts upload in 4 MiB chunks (matches terabox-api non-VIP behaviour).
        return 4 * 1024 * 1024;
    }

    protected function hashFile(string $localPath): array
    {
        $sliceSize = 256 * 1024;
        $chunkSize = $this->chunkSize(filesize($localPath));

        $fileMd5 = hash_init('md5');
        $sliceMd5 = hash_init('md5');
        $chunkMd5 = hash_init('md5');
        $crc = 0xFFFFFFFF;
        $chunks = [];

        $bytesTotal = 0;
        $bytesSlice = 0;
        $bytesChunk = 0;
        $sliceDone = false;

        $handle = fopen($localPath, 'rb');
        if ($handle === false) {
            throw new RuntimeException('Could not open local file for hashing.');
        }

        while (! feof($handle)) {
            $data = fread($handle, 1024 * 1024);
            if ($data === false || $data === '') {
                break;
            }

            hash_update($fileMd5, $data);

            // crc32 (IEEE reflected, matches crc-32 npm / PHP crc32())
            $len = strlen($data);
            for ($i = 0; $i < $len; $i++) {
                $crc = ($crc >> 8) ^ self::CRC_TABLE[($crc ^ ord($data[$i])) & 0xFF];
            }

            $offset = 0;
            $dlen = strlen($data);
            while ($offset < $dlen) {
                $remainingData = $dlen - $offset;
                $sliceRemaining = $sliceSize - $bytesSlice;
                $chunkRemaining = $chunkSize - $bytesChunk;
                $readLimit = $sliceDone
                    ? min($remainingData, $chunkRemaining)
                    : min($remainingData, $chunkRemaining, $sliceRemaining);

                $chunk = substr($data, $offset, $readLimit);
                hash_update($chunkMd5, $chunk);

                if (! $sliceDone) {
                    hash_update($sliceMd5, $chunk);
                }

                $offset += $readLimit;
                $bytesSlice += $readLimit;
                $bytesChunk += $readLimit;
                $bytesTotal += $readLimit;

                if ($bytesSlice >= $sliceSize) {
                    $sliceDone = true;
                }

                if ($bytesChunk >= $chunkSize) {
                    $chunks[] = hash_final($chunkMd5);
                    $chunkMd5 = hash_init('md5');
                    $bytesChunk = 0;
                }
            }
        }

        fclose($handle);

        if ($bytesChunk > 0) {
            $chunks[] = hash_final($chunkMd5);
        }

        return [
            'file' => hash_final($fileMd5),
            'slice' => hash_final($sliceMd5),
            'crc32' => $crc ^ 0xFFFFFFFF,
            'chunks' => $chunks,
        ];
    }

    protected function changeBase64Type(string $str, int $mode = 1): string
    {
        return $mode === 1
            ? str_replace(['+', '/'], ['-', '_'], $str)
            : str_replace(['-', '_'], ['+', '/'], $str);
    }

    protected function decryptAES(string $pp1, string $pp2): string
    {
        $pp1 = $this->changeBase64Type($pp1, 2);
        $pp2 = $this->changeBase64Type($pp2, 2);

        $iv = substr($pp1, 0, 16);
        $cipher = base64_decode(substr($pp1, 16));

        $decrypted = openssl_decrypt($cipher, 'aes-128-cbc', $pp2, OPENSSL_RAW_DATA, $iv);
        if ($decrypted === false) {
            throw new RuntimeException('AES decrypt of TeraBox public key failed.');
        }

        return $decrypted;
    }

    protected function encryptRSA(string $message, string $publicKeyPEM, int $mode = 1): string
    {
        if ($mode === 2) {
            $md5 = md5($message);
            $message = $md5 . ($md5 < 10 ? '0' : '') . strlen($md5);
        }

        $encrypted = '';
        $ok = openssl_public_encrypt($message, $encrypted, $publicKeyPEM, OPENSSL_PKCS1_PADDING);
        if (! $ok) {
            throw new RuntimeException('RSA encryption of password failed.');
        }

        return base64_encode($encrypted);
    }

    protected const CRC_TABLE = [
        0x00000000,0x77073096,0xEE0E612C,0x990951BA,0x076DC419,0x706AF48F,0xE963A535,0x9E6495A3,
        0x0EDB8832,0x79DCB8A4,0xE0D5E91E,0x97D2D988,0x09B64C2B,0x7EB17CBD,0xE7B82D07,0x90BF1D91,
        0x1DB71064,0x6AB020F2,0xF3B97148,0x84BE41DE,0x1ADAD47D,0x6DDDE4EB,0xF4D4B551,0x83D385C7,
        0x136C9856,0x646BA8C0,0xFD62F97A,0x8A65C9EC,0x14015C4F,0x63066CD9,0xFA0F3D63,0x8D080DF5,
        0x3B6E20C8,0x4C69105E,0xD56041E4,0xA2677172,0x3C03E4D1,0x4B04D447,0xD20D85FD,0xA50AB56B,
        0x35B5A8FA,0x42B2986C,0xDBBBC9D6,0xACBCF940,0x32D86CE3,0x45DF5C75,0xDCD60DCF,0xABD13D59,
        0x26D930AC,0x51DE003A,0xC8D75180,0xBFD06116,0x21B4F4B5,0x56B3C423,0xCFBA9599,0xB8BDA50F,
        0x2802B89E,0x5F058808,0xC60CD9B2,0xB10BE924,0x2F6F7C87,0x58684C11,0xC1611DAB,0xB6662D3D,
        0x76DC4190,0x01DB7106,0x98D220BC,0xEFD5102A,0x71B18589,0x06B6B51F,0x9FBFE4A5,0xE8B8D433,
        0x7807C9A2,0x0F00F934,0x9609A88E,0xE10E9818,0x7F6A0DBB,0x086D3D2D,0x91646C97,0xE6635C01,
        0x6B6B51F4,0x1C6C6162,0x856530D8,0xF262004E,0x6C0695ED,0x1B01A57B,0x8208F4C1,0xF50FC457,
        0x65B0D9C6,0x12B7E950,0x8BBEB8EA,0xFCB9887C,0x62DD1DDF,0x15DA2D49,0x8CD37CF3,0xFBD44C65,
        0x4DB26158,0x3AB551CE,0xA3BC0074,0xD4BB30E2,0x4ADFA541,0x3DD895D7,0xA4D1C46D,0xD3D6F4FB,
        0x4369E96A,0x346ED9FC,0xAD678846,0xDA60B8D0,0x44042D73,0x33031DE5,0xAA0A4C5F,0xDD0D7CC9,
        0x5005713C,0x270241AA,0xBE0B1010,0xC90C2086,0x5768B525,0x206F85B3,0xB966D409,0xCE61E49F,
        0x5EDEF90E,0x29D9C998,0xB0D09822,0xC7D7A8B4,0x59B33D17,0x2EB40D81,0xB7BD5C3B,0xC0BA6CAD,
        0xEDB88320,0x9ABFB3B6,0x03B6E20C,0x74B1D29A,0xEAD54739,0x9DD277AF,0x04DB2615,0x73DC1683,
        0xE3630B12,0x94643B84,0x0D6D6A3E,0x7A6A5AA8,0xE40ECF0B,0x9309FF9D,0x0A00AE27,0x7D079EB1,
        0xF00F9344,0x8708A3D2,0x1E01F268,0x6906C2FE,0xF762575D,0x806567CB,0x196C3671,0x6E6B06E7,
        0xFED41B76,0x89D32BE0,0x10DA7A5A,0x67DD4ACC,0xF9B9DF6F,0x8EBEEFF9,0x17B7BE43,0x60B08ED5,
        0xD6D6A3E8,0xA1D1937E,0x38D8C2C4,0x4FDFF252,0xD1BB67F1,0xA6BC5767,0x3FB506DD,0x48B2364B,
        0xD80D2BDA,0xAF0A1B4C,0x36034AF6,0x41047A60,0xDF60EFC3,0xA867DF55,0x316E8EEF,0x4669BE79,
        0xCB61B38C,0xBC66831A,0x256FD2A0,0x5268E236,0xCC0C7795,0xBB0B4703,0x220216B9,0x5505262F,
        0xC5BA3BBE,0xB2BD0B28,0x2BB45A92,0x5CB36A04,0xC2D7FFA7,0xB5D0CF31,0x2CD99E8B,0x5BDEAE1D,
        0x9B64C2B0,0xEC63F226,0x756AA39C,0x026D930A,0x9C0906A9,0xEB0E363F,0x72076785,0x05005713,
        0x95BF4A82,0xE2B87A14,0x7BB12BAE,0x0CB61B38,0x92D28E9B,0xE5D5BE0D,0x7CDCEFB7,0x0BDBDF21,
        0x86D3D2D4,0xF1D4E242,0x68DDB3F8,0x1FDA836E,0x81BE16CD,0xF6B9265B,0x6FB077E1,0x18B74777,
        0x88085AE6,0xFF0F6A70,0x66063BCA,0x11010B5C,0x8F659EFF,0xF862AE69,0x616BFFD3,0x166CCF45,
        0xA00AE278,0xD70DD2EE,0x4E048354,0x3903B3C2,0xA7672661,0xD06016F7,0x4969474D,0x3E6E77DB,
        0xAED16A4A,0xD9D65ADC,0x40DF0B66,0x37D83BF0,0xA9BCAE53,0xDEBB9EC5,0x47B2CF7F,0x30B5FFE9,
        0xBDBDF21C,0xCABAC28A,0x53B39330,0x24B4A3A6,0xBAD03605,0xCDD70693,0x54DE5729,0x23D967BF,
        0xB3667A2E,0xC4614AB8,0x5D681B02,0x2A6F2B94,0xB40BBE37,0xC30C8EA1,0x5A05DF1B,0x2D02EF8D,
    ];
}
