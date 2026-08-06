<?php
require __DIR__ . '/vendor/autoload.php';
$http = new GuzzleHttp\Client();
$res = $http->get('https://www.terabox.com/wap/outlogin/login', ['headers' => ['User-Agent' => 'Mozilla/5.0']]);
$html = (string) $res->getBody();
$pos = strpos($html, 'pcftoken');
echo substr($html, $pos - 400, 900) . "\n=====\n";
if (preg_match('/<script>var templateData = /', $html)) { echo 'regex template matches' . "\n"; }
