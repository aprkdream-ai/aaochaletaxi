<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

// Boots a release via CLI and requests key URLs before it goes live.
// Usage: php deploy/smoke.php <release_dir> <public_dir> <app_url>

[$script, $base, $public, $url] = $argv + [null, null, null, 'https://aaochaletaxi.com'];

require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->usePublicPath($public);
$kernel = $app->make(Kernel::class);

$failed = false;
foreach (['/up', '/', '/login', '/become-a-partner', '/sitemap.xml'] as $path) {
    $request = Request::create(rtrim($url, '/').$path, 'GET', server: ['HTTPS' => 'on']);
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    echo "  smoke {$path} -> {$status}\n";
    $failed = $failed || $status !== 200;
    $kernel->terminate($request, $response);
}

exit($failed ? 1 : 0);
