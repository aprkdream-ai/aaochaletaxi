<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Vite;

/** Manifest + service worker for the installable driver app (served by Laravel so the scope is /driver/). */
class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        return response()->json([
            'name' => 'AaoChaleTaxi Driver',
            'short_name' => 'Driver',
            'description' => 'Trips, OTP start and earnings for AaoChaleTaxi drivers',
            'start_url' => '/driver?source=pwa',
            'scope' => '/driver/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#0b1220',
            'theme_color' => '#0b1220',
            'icons' => [
                ['src' => asset('icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => asset('icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function serviceWorker(): Response
    {
        $assets = collect([Vite::asset('resources/scss/app.scss'), Vite::asset('resources/js/app.js'), asset('icon-192.png'), route('driver.offline')])->map(fn ($u) => parse_url($u, PHP_URL_PATH))->values();
        $version = substr(md5($assets->join('|')), 0, 10);

        $js = view('driver.sw', ['assets' => $assets, 'version' => $version])->render();

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/driver/',
            'Cache-Control' => 'no-cache',
        ]);
    }

    public function offline(): Response
    {
        return response()->view('driver.offline');
    }

    /** Public Firebase web config for push registration (null when not configured). */
    public static function webPushConfig(): ?array
    {
        $config = json_decode((string) PlatformSetting::getValue('fcm_web_config'), true);
        $vapid = PlatformSetting::getValue('fcm_vapid_key');

        return is_array($config) && $vapid && ! empty($config['projectId']) ? ['config' => array_intersect_key($config, array_flip(['apiKey', 'authDomain', 'projectId', 'messagingSenderId', 'appId'])), 'vapidKey' => $vapid] : null;
    }
}
