<?php

namespace App\Services;

/**
 * Minimal AWS SigV4 signer for Cloudflare R2's S3-compatible API.
 *
 * Only what the app needs:
 *   - presigned PUT  (browser uploads a chunk straight to the bucket)
 *   - presigned GET  (background worker downloads a chunk)
 *   - presigned DELETE (background worker cleans the bucket)
 *   - signed headers (CORS / bucket setup scripts)
 *
 * The signing math is plain PHP so this class can also be required from a
 * standalone CLI script (deploy/wasmer/r2-setup.php) without booting Laravel.
 */
class R2Presigner
{
    protected string $accountId;
    protected string $accessKeyId;
    protected string $secretAccessKey;
    protected string $bucket;
    protected string $endpoint;
    protected int $presignExpiry;

    /**
     * @param array|null $config When omitted, resolves from DB settings then
     *                           the r2 config file (Laravel runtime).
     */
    public function __construct(?array $config = null)
    {
        $config ??= [
            'account_id' => \App\Models\Setting::get('r2_account_id') ?: config('r2.account_id', ''),
            'access_key_id' => \App\Models\Setting::get('r2_access_key_id') ?: config('r2.access_key_id', ''),
            'secret_access_key' => \App\Models\Setting::get('r2_secret_access_key') ?: config('r2.secret_access_key', ''),
            'bucket' => \App\Models\Setting::get('r2_bucket') ?: config('r2.bucket', ''),
            'endpoint' => \App\Models\Setting::get('r2_endpoint') ?: config('r2.endpoint', ''),
            'presign_expiry' => config('r2.presign_expiry', 3600),
        ];

        $this->accountId = (string) ($config['account_id'] ?? '');
        $this->accessKeyId = (string) ($config['access_key_id'] ?? '');
        if ($this->accessKeyId === '') {
            $this->accessKeyId = $this->accountId;
        }
        $this->secretAccessKey = (string) ($config['secret_access_key'] ?? '');
        if (str_starts_with($this->secretAccessKey, 'cfat_')) {
            // Cloudflare API token used with the S3 API: the signing secret is
            // the SHA-256 hash of the token value and the access key id is the
            // token's id. See https://developers.cloudflare.com/r2/api/tokens/
            $this->secretAccessKey = hash('sha256', $this->secretAccessKey);
        }
        $this->bucket = (string) ($config['bucket'] ?? '');
        $this->endpoint = rtrim((string) ($config['endpoint'] ?? ''), '/');
        if ($this->endpoint === '') {
            $this->endpoint = 'https://' . $this->accountId . '.r2.cloudflarestorage.com';
        }
        $this->presignExpiry = max(300, (int) ($config['presign_expiry'] ?? 3600));
    }

    public function isConfigured(): bool
    {
        return $this->accountId !== '' && $this->secretAccessKey !== '' && $this->bucket !== '';
    }

    public function isEnabled(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $enabled = \App\Models\Setting::get('r2_enabled');
        if ($enabled !== null) {
            return in_array(strtolower(trim((string) $enabled)), ['1', 'true', 'yes', 'on'], true);
        }

        return (bool) config('r2.enabled', false);
    }

    public function accountId(): string
    {
        return $this->accountId;
    }

    public function bucket(): string
    {
        return $this->bucket;
    }

    public function endpoint(): string
    {
        return $this->endpoint;
    }

    /**
     * Object key for one chunk inside the bucket.
     */
    public function chunkKey(string $uploadToken, int $chunkIndex): string
    {
        return 'chunks/' . $uploadToken . '/' . $chunkIndex;
    }

    public function presignPut(string $key, ?int $expires = null): string
    {
        return $this->presign('PUT', $key, $expires);
    }

    public function presignGet(string $key, ?int $expires = null): string
    {
        return $this->presign('GET', $key, $expires);
    }

    public function presignDelete(string $key, ?int $expires = null): string
    {
        return $this->presign('DELETE', $key, $expires);
    }

    /**
     * Presigned URL (query-string auth, payload unsigned).
     */
    public function presign(string $method, string $key, ?int $expires = null): string
    {
        $expires = $expires ?? $this->presignExpiry;
        $now = gmdate('Ymd\THis\Z');
        $shortDate = substr($now, 0, 8);
        $region = 'auto';
        $service = 's3';
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);

        $uri = '/' . $this->bucket . '/' . $this->uriEncode($key);

        $query = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->accessKeyId . '/' . $shortDate . '/' . $region . '/' . $service . '/aws4_request',
            'X-Amz-Date' => $now,
            'X-Amz-Expires' => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($query);
        $canonicalQuery = $this->canonicalQueryString($query);

        $canonicalHeaders = 'host:' . $host . "\n";
        $signedHeaders = 'host';

        $canonicalRequest = $method . "\n" . $uri . "\n" . $canonicalQuery . "\n" . $canonicalHeaders . "\n" . $signedHeaders . "\nUNSIGNED-PAYLOAD";

        $stringToSign = "AWS4-HMAC-SHA256\n" . $now . "\n" . $shortDate . '/' . $region . '/' . $service . "/aws4_request\n" . hash('sha256', $canonicalRequest);
        $signature = $this->signature($stringToSign, $shortDate, $region, $service);

        $query['X-Amz-Signature'] = $signature;
        ksort($query);

        return $this->endpoint . $uri . '?' . $this->canonicalQueryString($query);
    }

    /**
     * Signed headers for a raw S3 API call (used by setup/CLI scripts).
     *
     * @return array{url: string, headers: array<string,string>}
     */
    public function signedRequest(string $method, string $uri, string $payload = '', array $query = [], array $extraHeaders = []): array
    {
        $now = gmdate('Ymd\THis\Z');
        $shortDate = substr($now, 0, 8);
        $region = 'auto';
        $service = 's3';
        $host = (string) parse_url($this->endpoint, PHP_URL_HOST);

        $canonicalUri = '/' . $this->bucket . $uri;
        $canonicalQuery = $this->canonicalQueryString($query);

        $payloadHash = hash('sha256', $payload);

        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];
        foreach ($extraHeaders as $name => $value) {
            $headers[strtolower((string) $name)] = (string) $value;
        }
        $canonicalHeaders = '';
        $signedHeaderNames = [];
        foreach ($headers as $name => $value) {
            $canonicalHeaders .= $name . ':' . trim($value) . "\n";
            $signedHeaderNames[] = $name;
        }
        $signedHeaders = implode(';', $signedHeaderNames);

        $canonicalRequest = $method . "\n" . $canonicalUri . "\n" . $canonicalQuery . "\n" . $canonicalHeaders . "\n" . $signedHeaders . "\n" . $payloadHash;
        $stringToSign = "AWS4-HMAC-SHA256\n" . $now . "\n" . $shortDate . '/' . $region . '/' . $service . "/aws4_request\n" . hash('sha256', $canonicalRequest);
        $signature = $this->signature($stringToSign, $shortDate, $region, $service);

        $outHeaders = [
            'Authorization' => 'AWS4-HMAC-SHA256 Credential=' . $this->accessKeyId . '/' . $shortDate . '/' . $region . '/' . $service . '/aws4_request, SignedHeaders=' . $signedHeaders . ', Signature=' . $signature,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $now,
        ];
        foreach ($headers as $name => $value) {
            if (! array_key_exists($name, ['host' => true])) {
                $outHeaders[$name] = $value;
            }
        }

        return [
            'url' => $this->endpoint . $canonicalUri . ($canonicalQuery !== '' ? '?' . $canonicalQuery : ''),
            'headers' => $outHeaders,
        ];
    }

    protected function signature(string $stringToSign, string $shortDate, string $region, string $service): string
    {
        $kDate = hash_hmac('sha256', $shortDate, 'AWS4' . $this->secretAccessKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        return hash_hmac('sha256', $stringToSign, $kSigning, false);
    }

    protected function canonicalQueryString(array $query): string
    {
        ksort($query);
        $parts = [];
        foreach ($query as $key => $value) {
            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode((string) $value);
        }

        return implode('&', $parts);
    }

    protected function uriEncode(string $path): string
    {
        $segments = explode('/', $path);

        return implode('/', array_map('rawurlencode', $segments));
    }
}
