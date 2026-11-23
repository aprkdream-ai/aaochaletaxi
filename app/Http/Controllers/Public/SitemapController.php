<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\TenantServiceCity;
use App\Services\Marketplace\MarketplaceService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $routes = ['home' => '1.0', 'about' => '0.6', 'services' => '0.8', 'partner' => '0.8', 'contact' => '0.6',
            'legal.privacy' => '0.3', 'legal.terms' => '0.3', 'legal.refund' => '0.3', 'legal.shipping' => '0.3'];

        $urls = collect($routes)->map(fn ($priority, $name) => ['loc' => route($name), 'priority' => $priority])->values()
            ->push(['loc' => route('marketplace.search'), 'priority' => '0.8'])
            ->merge(collect(app(MarketplaceService::class)->listedCities())
                ->map(fn ($c) => ['loc' => route('marketplace.city', TenantServiceCity::key($c)), 'priority' => '0.7']));

        return response()->view('public.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }
}
