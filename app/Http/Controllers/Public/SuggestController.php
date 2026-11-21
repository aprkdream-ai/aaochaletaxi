<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Maps\PlaceSuggestService;
use App\Support\India;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Autosuggest endpoints for pickup/drop and city fields (JSON, throttled). */
class SuggestController extends Controller
{
    public function places(Request $request, PlaceSuggestService $places): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'token' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->json(['items' => $places->suggest($data['q'], $request->user(), $data['token'] ?? null)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function cities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
        ]);
        $state = India::normalizeState($data['state'] ?? null);
        $q = (string) ($data['q'] ?? '');
        $items = ($q === '' && ! $state) ? [] : array_map(
            fn ($c) => ['label' => $c['city'], 'sub' => $state ? null : $c['state'], 'value' => $c['city'], 'state' => $c['state'], 'source' => 'city'],
            India::searchCities($q, $state, $q === '' ? 60 : 10),
        );

        return response()->json(['items' => $items])->header('Cache-Control', 'public, max-age=86400');
    }
}
