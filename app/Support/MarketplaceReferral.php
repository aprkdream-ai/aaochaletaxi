<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Http\Request;

/** Remembers (2 h, session) that a customer opened an operator's booking page from the marketplace, for commission. */
class MarketplaceReferral
{
    private const KEY = 'marketplace_ref';

    public static function remember(Request $request, Tenant $tenant): void
    {
        $request->session()->put(self::KEY, ['tenant' => $tenant->id, 'until' => now()->addHours(2)->timestamp]);
    }

    public static function matches(Request $request, Tenant $tenant): bool
    {
        $ref = $request->session()->get(self::KEY);

        return is_array($ref) && ($ref['tenant'] ?? null) === $tenant->id && ($ref['until'] ?? 0) > now()->timestamp;
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }
}
