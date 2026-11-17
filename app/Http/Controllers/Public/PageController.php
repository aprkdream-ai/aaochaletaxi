<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use App\Support\Money;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('public.home', ['startingPrice' => $this->startingPrice()]);
    }

    private function startingPrice(): ?string
    {
        $min = SubscriptionPlan::query()->active()->min('price_monthly');

        return $min !== null ? Money::format($min, 0) : null;
    }

    public function about(): View
    {
        return view('public.about');
    }

    public function services(): View
    {
        return view('public.services');
    }

    public function partner(): View
    {
        return view('public.partner', ['plans' => SubscriptionPlan::query()->active()->get()]);
    }

    public function contact(): View
    {
        return view('public.contact');
    }

    public function legal(string $page): View
    {
        return view('public.legal.'.$page);
    }
}
