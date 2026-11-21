<?php

namespace App\Services\Maps;

use App\Enums\UserType;
use App\Models\Booking;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\India;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pickup / drop suggestions, merged from:
 *  1. the operator's own recent booking addresses (operator & vendor users only — never shown on public pages),
 *  2. Google Places Autocomplete (New) when the server key has Places API enabled (cached; switches itself off for an hour on auth errors),
 *     or, without a Google key, free OpenStreetMap search (Photon),
 *  3. the built-in list of Indian cities (always available).
 */
class PlaceSuggestService
{
    private const OFF_FLAG = 'maps:places:off';

    /** @return list<array{label:string, sub:?string, value:string, source:string}> */
    public function suggest(string $query, ?User $user, ?string $sessionToken = null, int $limit = 8): array
    {
        $q = trim(preg_replace('/\s+/', ' ', $query));
        if (mb_strlen($q) < 2) {
            return [];
        }

        $out = [];
        $seen = [];
        $push = function (array $item) use (&$out, &$seen) {
            $key = mb_strtolower($item['value']);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $item;
            }
        };

        foreach ($this->recent($q, $user) as $item) {
            $push($item);
        }
        $places = $this->google($q, $sessionToken);
        if (! $places) {
            $places = array_map(fn ($i) => array_diff_key($i, ['lat' => 1, 'lng' => 1]), app(OsmService::class)->autocomplete($q));
        }
        foreach ($places as $item) {
            $push($item);
        }
        if (count($out) < $limit) {
            foreach (India::searchCities($q, null, $limit) as $c) {
                $push(['label' => $c['city'], 'sub' => $c['state'], 'value' => $c['city'].', '.$c['state'], 'source' => 'city']);
            }
        }

        return array_slice($out, 0, $limit);
    }

    public function googleEnabled(): bool
    {
        return filled(PlatformSetting::getValue('google_maps_api_key')) && ! Cache::has(self::OFF_FLAG);
    }

    /** @return list<array{label:string, sub:?string, value:string, source:string}> */
    private function recent(string $q, ?User $user): array
    {
        if (! $user?->tenant_id || ! in_array($user->type, [UserType::Operator, UserType::Vendor], true)) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], '', $q).'%';
        $rows = [];
        foreach (['pickup_address', 'drop_address'] as $col) {
            $rows = array_merge($rows, Booking::withoutGlobalScopes()
                ->where('tenant_id', $user->tenant_id)->where($col, 'like', $like)
                ->latest('id')->limit(15)->pluck($col)->all());
        }

        $items = [];
        foreach (array_unique(array_filter($rows)) as $address) {
            $items[] = ['label' => $address, 'sub' => null, 'value' => $address, 'source' => 'recent'];
            if (count($items) === 3) {
                break;
            }
        }

        return $items;
    }

    /** @return list<array{label:string, sub:?string, value:string, source:string}> */
    private function google(string $q, ?string $sessionToken): array
    {
        if (! $this->googleEnabled()) {
            return [];
        }

        $cacheKey = 'maps:ac:'.md5(mb_strtolower($q));
        if (($cached = Cache::get($cacheKey)) !== null) {
            return $cached;
        }

        try {
            $body = ['input' => $q, 'includedRegionCodes' => ['in'], 'languageCode' => app()->getLocale() === 'hi' ? 'hi' : 'en'];
            if ($sessionToken && preg_match('/^[A-Za-z0-9-]{8,64}$/', $sessionToken)) {
                $body['sessionToken'] = $sessionToken;
            }
            $r = Http::timeout(4)->withHeaders(['X-Goog-Api-Key' => (string) PlatformSetting::getValue('google_maps_api_key')])
                ->post('https://places.googleapis.com/v1/places:autocomplete', $body);
        } catch (Throwable) {
            return [];
        }

        if (in_array($r->status(), [400, 401, 403], true)) {
            Cache::put(self::OFF_FLAG, true, now()->addHour());
            Log::warning('Google Places autocomplete unavailable', ['status' => $r->status(), 'error' => $r->json('error.status')]);

            return [];
        }
        if (! $r->successful()) {
            return [];
        }

        $items = [];
        foreach ((array) $r->json('suggestions', []) as $s) {
            $p = $s['placePrediction'] ?? null;
            if (! $p || empty($p['text']['text'])) {
                continue;
            }
            $items[] = [
                'label' => $p['structuredFormat']['mainText']['text'] ?? $p['text']['text'],
                'sub' => $p['structuredFormat']['secondaryText']['text'] ?? null,
                'value' => $p['text']['text'],
                'source' => 'google',
            ];
        }
        Cache::put($cacheKey, $items, now()->addDays(7));

        return $items;
    }

    /** @return array{ok:bool, message:string} */
    public function test(): array
    {
        Cache::forget(self::OFF_FLAG);
        Cache::forget('maps:ac:'.md5('indore airport'));
        $items = $this->google('Indore airport', null);

        return $items
            ? ['ok' => true, 'message' => __('Places autocomplete: :n suggestions.', ['n' => count($items)])]
            : ['ok' => false, 'message' => __('Places autocomplete failed — enable "Places API (New)" for this key. Built-in city suggestions are used meanwhile.')];
    }
}
