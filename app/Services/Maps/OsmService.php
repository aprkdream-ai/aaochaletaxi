<?php

namespace App\Services\Maps;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Free OpenStreetMap services, used when no Google key is set:
 *  - Photon (photon.komoot.io) for place autocomplete / geocoding,
 *  - OSRM (router.project-osrm.org) for road distance.
 * Both are public fair-use servers without an SLA: results are cached long, requests time out fast, and each service
 * switches itself off for 10 minutes after an error or rate limit. Callers always have a fallback.
 */
class OsmService
{
    private const PHOTON = 'https://photon.komoot.io/api/';

    private const OSRM = 'https://router.project-osrm.org/route/v1/driving/';

    private const INDIA_BBOX = '68.1,6.5,97.4,35.7';

    public function isEnabled(): bool
    {
        return PlatformSetting::getValue('maps_free', 'on') === 'on';
    }

    /** @return list<array{label:string, sub:?string, value:string, source:string, lat:float, lng:float}> */
    public function autocomplete(string $q, int $limit = 6): array
    {
        if (! $this->isEnabled() || Cache::has('maps:photon:off')) {
            return [];
        }

        $key = 'maps:photon:'.md5(mb_strtolower(trim($q)));
        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        $r = $this->get(self::PHOTON, ['q' => $q, 'limit' => $limit + 4, 'lang' => 'en', 'bbox' => self::INDIA_BBOX], 'maps:photon:off');
        if ($r === null) {
            return [];
        }

        $items = [];
        $seen = [];
        foreach ((array) ($r['features'] ?? []) as $f) {
            $p = $f['properties'] ?? [];
            if (($p['countrycode'] ?? null) !== 'IN' || empty($p['name'])) {
                continue;
            }
            $area = array_values(array_unique(array_filter([
                $p['district'] ?? null, $p['locality'] ?? null, $p['city'] ?? ($p['county'] ?? null), $p['state'] ?? null,
            ], fn ($v) => $v && $v !== $p['name'])));
            $value = implode(', ', array_merge([$p['name']], $area));
            if (isset($seen[mb_strtolower($value)])) {
                continue;
            }
            $seen[mb_strtolower($value)] = true;
            $items[] = [
                'label' => $p['name'], 'sub' => $area ? implode(', ', $area) : null, 'value' => $value, 'source' => 'place',
                'lat' => (float) $f['geometry']['coordinates'][1], 'lng' => (float) $f['geometry']['coordinates'][0],
            ];
            if (count($items) === $limit) {
                break;
            }
        }
        Cache::put($key, $items, now()->addDays(7));

        return $items;
    }

    /** @return array{lat:float, lng:float, label:string}|null */
    public function geocode(string $address): ?array
    {
        $first = $this->autocomplete($address, 1)[0] ?? null;

        return $first ? ['lat' => $first['lat'], 'lng' => $first['lng'], 'label' => $first['value']] : null;
    }

    /** @return array{km: float, minutes: int, origin: string, destination: string}|null */
    public function distance(string $origin, string $destination): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }
        $key = 'maps:osrm:'.md5(mb_strtolower(trim($origin)).'|'.mb_strtolower(trim($destination)));
        if (($cached = Cache::get($key)) !== null) {
            return $cached;
        }

        $a = $this->geocode($origin);
        $b = $this->geocode($destination);
        if (! $a || ! $b || Cache::has('maps:osrm:off')) {
            return null;
        }

        $coords = sprintf('%.6F,%.6F;%.6F,%.6F', $a['lng'], $a['lat'], $b['lng'], $b['lat']);
        $r = $this->get(self::OSRM.$coords, ['overview' => 'false'], 'maps:osrm:off');
        $route = $r['routes'][0] ?? null;
        if (($r['code'] ?? null) !== 'Ok' || ! $route) {
            return null;
        }

        $result = [
            'km' => round($route['distance'] / 1000, 1),
            // OSRM assumes free-flowing roads; Indian highway travel is ~1.4× slower in practice.
            'minutes' => (int) round($route['duration'] / 60 * 1.4),
            'origin' => $a['label'], 'destination' => $b['label'],
        ];
        Cache::put($key, $result, now()->addDays(30));

        return $result;
    }

    private function get(string $url, array $query, string $offFlag): ?array
    {
        try {
            $r = Http::timeout(4)->withUserAgent(config('app.name').'/1.0 (+'.config('app.url').')')->get($url, $query);
        } catch (Throwable) {
            Cache::put($offFlag, true, now()->addMinutes(10));

            return null;
        }
        if ($r->status() === 429 || $r->serverError()) {
            Cache::put($offFlag, true, now()->addMinutes(10));

            return null;
        }
        if (! $r->successful() && $r->status() !== 400) { // OSRM answers 400 for "no route" — not a service failure

            return null;
        }

        return $r->json();
    }
}
