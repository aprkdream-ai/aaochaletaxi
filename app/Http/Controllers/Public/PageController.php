<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\View\View;

class PageController extends Controller
{
    public function home(): View
    {
        return view('public.home');
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
