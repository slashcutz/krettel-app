<?php

declare(strict_types=1);

// Verifies the Cloudflare R2 S3 credentials and installs the bucket CORS
// policy that lets the browser PUT video chunks straight to Cloudflare's
// edge (fast staging uploads). Standalone — no Laravel boot required.
//
// Usage (from repo root):
//   php deploy/wasmer/r2-setup.php [--account-id=...] [--access-key-id=...]
//       [--secret-access-key=...] [--bucket=...] [--endpoint=...] [--origins=a,b]
//
// Credentials fall back to R2_* environment variables / .env. When the access
// key id is empty it defaults to the account id (Cloudflare API-token style,
// cfat_... tokens).

require __DIR__ . '/../../app/Services/R2Presigner.php';

use App\Services\R2Presigner;

$args = [];
foreach (array_slice($argv, 1) as $raw) {
    if (str_starts_with($raw, '--')) {
        $parts = explode('=', substr($raw, 2), 2);
        $args[$parts[0]] = $parts[1] ?? 'true';
    }
}

function argEnv(array $args, string $name, string $default = ''): string
{
    if (isset($args[$name]) && $args[$name] !== 'true') {
        return (string) $args[$name];
    }

    return getenv('R2_' . strtoupper(str_replace('-', '_', $name))) ?: $default;
}

$config = [
    'account_id' => argEnv($args, 'account-id'),
    'access_key_id' => argEnv($args, 'access-key-id'),
    'secret_access_key' => argEnv($args, 'secret-access-key'),
    'bucket' => argEnv($args, 'bucket'),
    'endpoint' => argEnv($args, 'endpoint'),
    'presign_expiry' => 3600,
];

$presigner = new R2Presigner($config);

if (! $presigner->isConfigured()) {
    fwrite(STDERR, "Missing R2 credentials. Set R2_ACCOUNT_ID, R2_SECRET_ACCESS_KEY and R2_BUCKET via env or --flags.\n");
    exit(1);
}

$secret = $config['secret_access_key'];
$secretPreview = strlen($secret) > 8 ? substr($secret, 0, 8) . '...' : '****';
$accessDisplay = $config['access_key_id'] !== '' ? $config['access_key_id'] : $presigner->accountId() . ' (fallback)';

echo "R2 config\n";
echo "  Account ID : {$presigner->accountId()}\n";
echo "  Access key : {$accessDisplay}\n";
echo "  Secret     : {$secretPreview}\n";
echo "  Bucket     : {$presigner->bucket()}\n";
echo "  Endpoint   : {$presigner->endpoint()}\n";

// 1. Verify the credentials with ListObjectsV2.
echo "\n[1/3] Verifying credentials (ListObjectsV2)...\n";
$request = $presigner->signedRequest('GET', '/', '', ['list-type' => '2', 'max-keys' => '1']);
$ch = curl_init($request['url']);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array_map(fn (string $v, string $k) => $k . ': ' . $v, $request['headers'], array_keys($request['headers'])),
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($status === 401 || $status === 403) {
    fwrite(STDERR, "FAILED (HTTP {$status}) — " . ($error !== '' ? $error : $body) . "\n");
    fwrite(STDERR, "The R2 credentials were rejected.\n");
    fwrite(STDERR, "  - HTTP 401 = wrong access key id / secret pair.\n");
    fwrite(STDERR, "  - HTTP 403 = credentials accepted but no permission for this bucket.\n");
    fwrite(STDERR, "Fix: create an R2 API token in the Cloudflare dashboard (R2 > Manage API tokens >\n");
    fwrite(STDERR, "Create API token) with 'Object Read & Write' permission applied to the 'krettel'\n");
    fwrite(STDERR, "bucket, then pass its Access Key ID + Secret Access Key via --access-key-id/--secret-access-key\n");
    fwrite(STDERR, "or R2_ACCESS_KEY_ID/R2_SECRET_ACCESS_KEY env vars.\n");
    exit(1);
}
if ($status !== 200) {
    fwrite(STDERR, "FAILED (HTTP {$status}) — " . ($error !== '' ? $error : $body) . "\n");
    exit(1);
}
echo "OK (HTTP 200). Credentials accepted.\n";

// 2. Install the bucket CORS policy.
$origins = $args['origins'] ?? 'https://krettel-app.wasmer.app,http://localhost:8000,http://localhost:5173';
$originList = array_map('trim', explode(',', $origins));

$allowedOrigins = '';
foreach ($originList as $origin) {
    $allowedOrigins .= "      <AllowedOrigin>{$origin}</AllowedOrigin>\n";
}

$corsXml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
    "<CORSConfiguration xmlns=\"http://s3.amazonaws.com/doc/2006-03-01/\">\n" .
    "  <CORSRule>\n" .
    $allowedOrigins .
    "      <AllowedMethod>GET</AllowedMethod>\n" .
    "      <AllowedMethod>PUT</AllowedMethod>\n" .
    "      <AllowedMethod>POST</AllowedMethod>\n" .
    "      <AllowedMethod>DELETE</AllowedMethod>\n" .
    "      <AllowedMethod>HEAD</AllowedMethod>\n" .
    "      <AllowedHeader>*</AllowedHeader>\n" .
    "      <ExposeHeader>ETag</ExposeHeader>\n" .
    "      <MaxAgeSeconds>3600</MaxAgeSeconds>\n" .
    "  </CORSRule>\n" .
    "</CORSConfiguration>\n";

echo "\n[2/3] Installing bucket CORS policy...\n";
echo "  Origins: " . implode(', ', $originList) . "\n";

$request = $presigner->signedRequest('PUT', '/', $corsXml, [], ['Content-Type' => 'application/xml']);
$ch = curl_init($request['url']);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_POSTFIELDS => $corsXml,
    CURLOPT_HTTPHEADER => array_map(fn (string $v, string $k) => $k . ': ' . $v, $request['headers'], array_keys($request['headers'])),
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($status !== 200) {
    fwrite(STDERR, "FAILED (HTTP {$status}) — " . ($error !== '' ? $error : $body) . "\n");
    exit(1);
}
echo "OK (HTTP 200). CORS policy installed.\n";

// 3. Read the policy back so we can see exactly what the bucket has.
echo "\n[3/3] Reading back CORS policy...\n";
$request = $presigner->signedRequest('GET', '/', '', ['cors' => '']);
$ch = curl_init($request['url']);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => array_map(fn (string $v, string $k) => $k . ': ' . $v, $request['headers'], array_keys($request['headers'])),
    CURLOPT_TIMEOUT => 30,
]);
$body = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($status === 200) {
    echo $body . "\n";
} else {
    echo "Could not read back the policy (HTTP {$status}). It may still be applied.\n";
}

echo "\nDone. The bucket is ready for browser -> R2 -> server staging uploads.\n";
