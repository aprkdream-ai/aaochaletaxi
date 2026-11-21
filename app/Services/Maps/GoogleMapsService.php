<?php

namespace App\Services\Maps;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Road distance via Google Distance Matrix (cached 30 days). Returns null when unavailable — callers fall back to manual km. */
class GoogleMapsService
{
    public function isEnabled(): bool
    {
        return filled(PlatformSetting::getValue('google_maps_api_key'));
    }

    /** @return array{km: float, minutes: int, origin: string, destination: string}|null */
    public function distance(string $origin, string $destination): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $key = 'maps:dm:'.md5(mb_strtolower(trim($origin)).'|'.mb_strtolower(trim($destination)));

        return Cache::remember($key, now()->addDays(30), function () use ($origin, $destination) {
            try {
                $r = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                    'origins' => $origin, 'destinations' => $destination, 'units' => 'metric', 'region' => 'in',
                    'key' => PlatformSetting::getValue('google_maps_api_key'),
                ]);
                $el = $r->json('rows.0.elements.0');
                if ($r->json('status') !== 'OK' || ($el['status'] ?? null) !== 'OK') {
                    return null;
                }

                return [
                    'km' => round($el['distance']['value'] / 1000, 1),
                    'minutes' => (int) round($el['duration']['value'] / 60),
                    'origin' => (string) $r->json('origin_addresses.0'),
                    'destination' => (string) $r->json('destination_addresses.0'),
                ];
            } catch (Throwable) {
                return null;
            }
        });
    }

    /** @return array{ok:bool, message:string} */
    public function test(): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => __('Server key is not set.')];
        }
        Cache::forget('maps:dm:'.md5('mumbai|pune'));
        $d = $this->distance('Mumbai', 'Pune');

        return $d
            ? ['ok' => true, 'message' => __('Mumbai → Pune: :km km, about :h h :m min.', ['km' => $d['km'], 'h' => intdiv($d['minutes'], 60), 'm' => $d['minutes'] % 60])]
            : ['ok' => false, 'message' => __('Distance Matrix request failed. Check the key and that Distance Matrix API is enabled with billing.')];
    }
}
