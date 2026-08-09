<?php

declare(strict_types=1);

// Router for PHP's built-in web server (used by Wasmer Edge; see wasmer.toml).
// Replicates Laravel's public/.htaccess front-controller behaviour in pure PHP:
// - existing files (public assets) are served directly
// - everything else is routed through the front controller public/index.php

$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$file = __DIR__ . '/../public' . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/../public/index.php';