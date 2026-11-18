<?php

// AAOCHALETAXI_FRONT_CONTROLLER — managed by deploy/remote-deploy.sh. Do not edit on the server.
// The Laravel app lives outside the web root in ../aaochaletaxi_app/current (release symlink).

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Resolve the `current` symlink ourselves: PHP's realpath cache (long TTL on LiteSpeed) would otherwise keep
// serving the previous release for minutes after a deploy. Including the release by its full path also gives
// OPcache fresh keys per release.
$appRoot = __DIR__.'/../aaochaletaxi_app';
$release = @readlink($appRoot.'/current');
$appBase = $release ? (str_starts_with($release, '/') ? $release : $appRoot.'/'.$release) : $appRoot.'/current';

if (file_exists($maintenance = $appBase.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

require $appBase.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $appBase.'/bootstrap/app.php';

$app->usePublicPath(__DIR__);

$app->handleRequest(Request::capture());
