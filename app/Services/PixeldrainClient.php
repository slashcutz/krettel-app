<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PixeldrainClient
{
    protected Client $http;
    protected $progressCallback = null;
    protected int $progressTotal = 0;
    protected int $progressUploaded = 0;

    public function __construct()
    {
        $this->http = new Client([
            'timeout' => 7200,
            'allow_redirects' => ['max' => 5, 'strict' => true, 'referer' => true],
        ]);
    }

    public function baseUrl(): string
    {
        return rtrim(\App\Models\Setting::get('pixeldrain_base_url')
            ?: config('pixeldrain.base_url', 'https://pixeldrain.net'), '/');
    }

    public function apiKey(): ?string
    {
        return \App\Models\Setting::get('pixeldrain_api_key')
            ?: config('pixeldrain.api_key');
    }

    /**
     * HTTP auth option for Guzzle. Pixeldrain requires the API key in the
     * Basic auth password field; the username may be empty.
     */
    protected function auth(): array
    {
        $key = $this->apiKey();

        return $key ? ['', $key, 'basic'] : ['', '', 'basic'];
    }

    /**
     * Raw "Authorization: Basic ..." header line, for fopen() stream contexts
     * that cannot use Guzzle's auth option.
     */
    public function authHeader(): ?string
    {
        $key = $this->apiKey();

        return $key ? 'Authorization: Basic ' . base64_encode(':' . $key) : null;
    }

    /**
     * Register a callback fired as upload progress changes.
     * Signature: fn(int $bytesUploaded, int $totalBytes).
     */
    public function onProgress(callable $callback): void
    {
        $this->progressCallback = $callback;
    }

    protected function emitProgress(): void
    {
        if ($this->progressCallback && $this->progressTotal > 0) {
            call_user_func($this->progressCallback, $this->progressUploaded, $this->progressTotal);
        }
    }

    /**
     * Absolute URL for a file on the pixeldrain host (for proxying).
     */
    public function fileUrl(string $id): string
    {
        return $this->baseUrl() . '/api/file/' . $id;
    }

    /**
     * Fetch file metadata (name, size, mime, available, expiry...).
     *
     * A successful response also confirms the file still exists, which is
     * used by the keep-alive job to prevent the 60-day idle expiry.
     */
    public function getFileInfo(string $id): array
    {
        $response = $this->http->request('GET', $this->baseUrl() . '/api/file/' . $id . '/info', [
            'auth' => $this->auth(),
        ]);

        $body = json_decode((string) $response->getBody(), true);

        if (! is_array($body)) {
            throw new RuntimeException('Pixeldrain returned an invalid response for file ' . $id . '.');
        }

        if (isset($body['success']) && $body['success'] === false) {
            throw new RuntimeException('Pixeldrain error: ' . ($body['value'] ?? 'unknown') . ' (file ' . $id . ').');
        }

        return $body;
    }

    /**
     * Keep-alive: reset the idle-expiry timer for a file.
     *
     * The download endpoint resets the 60-day expiry clock. We only request a
     * single byte so we do not count meaningfully against the daily download
     * cap while still signalling the file as active.
     */
    public function touch(string $id): bool
    {
        try {
            $response = $this->http->request('GET', $this->baseUrl() . '/api/file/' . $id, [
                'auth' => $this->auth(),
                'headers' => ['Range' => 'bytes=0-0', 'Accept' => '*/*'],
            ]);

            if ($response->getStatusCode() === 200 || $response->getStatusCode() === 206) {
                return true;
            }

            Log::warning('[PIXELDRAIN-KEEPALIVE] Unexpected status ' . $response->getStatusCode() . ' for file ' . $id . '.');
        } catch (\Throwable $e) {
            Log::warning('[PIXELDRAIN-KEEPALIVE] Failed to touch file ' . $id . ': ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Fetch the raw bytes + content type of a file (used to proxy stored images).
     */
    public function downloadBytes(string $id): array
    {
        $response = $this->http->request('GET', $this->fileUrl($id), [
            'auth' => $this->auth(),
        ]);

        $body = (string) $response->getBody();

        if ($body === '') {
            throw new RuntimeException('Pixeldrain returned an empty payload for file ' . $id . '.');
        }

        $type = $response->getHeaderLine('Content-Type') ?: 'image/jpeg';

        return ['body' => $body, 'type' => $type];
    }

    /**
     * Upload a file to Pixeldrain (multipart, field "file").
     *
     * @return string  the new file ID
     */
    public function upload(string $absolutePath, string $filename): string
    {
        if (! $this->apiKey()) {
            throw new RuntimeException('Pixeldrain API key is not configured.');
        }

        if (! is_file($absolutePath)) {
            throw new RuntimeException('File not found: ' . $absolutePath);
        }

        $this->progressTotal = filesize($absolutePath);
        $this->progressUploaded = 0;

        $response = $this->http->request('POST', $this->baseUrl() . '/api/file', [
            'auth' => $this->auth(),
            'multipart' => [
                [
                    'name' => 'file',
                    'contents' => fopen($absolutePath, 'rb'),
                    'filename' => $filename,
                ],
            ],
            'progress' => function ($downloadTotal, $downloadedBytes, $uploadTotal, $uploadedBytes) {
                $this->progressUploaded = $uploadedBytes;
                $this->progressTotal = $uploadTotal ?: $this->progressTotal;
                $this->emitProgress();
            },
        ]);

        $body = json_decode((string) $response->getBody(), true);

        if (! is_array($body) || ! isset($body['success']) || $body['success'] !== true) {
            throw new RuntimeException('Pixeldrain upload failed: ' . json_encode($body));
        }

        $id = (string) ($body['id'] ?? '');

        if ($id === '') {
            throw new RuntimeException('Pixeldrain upload succeeded but returned no file ID.');
        }

        return $id;
    }

    /**
     * Delete a file from Pixeldrain (used when a video is removed).
     */
    public function delete(string $id): bool
    {
        try {
            $response = $this->http->request('DELETE', $this->baseUrl() . '/api/file/' . $id, [
                'auth' => $this->auth(),
            ]);

            $body = json_decode((string) $response->getBody(), true);

            return is_array($body) && ($body['success'] ?? false) === true;
        } catch (\Throwable $e) {
            Log::warning('[PIXELDRAIN-DELETE] Failed to delete file ' . $id . ': ' . $e->getMessage());
        }

        return false;
    }

    /**
     * Check whether credentials are configured at all (settings UI helper).
     */
    public function isConfigured(): bool
    {
        return (bool) $this->apiKey();
    }
}
