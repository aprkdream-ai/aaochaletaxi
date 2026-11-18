<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\PlatformSetting;
use App\Models\PlatformSettlement;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\Finance\PlatformSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function settlements(PlatformSettlementService $service): View
    {
        return view('admin.finance.settlements', [
            'outstanding' => $service->outstanding(),
            'history' => PlatformSettlement::query()->with(['tenant:id,uuid,name', 'creator:id,name'])->latest('id')->limit(30)->get(),
            'feePercent' => PlatformSetting::getValue('online_collection_fee_percent', '2'),
        ]);
    }

    public function settle(Request $request, Tenant $tenant, PlatformSettlementService $service): RedirectResponse
    {
        $data = $request->validate([
            'fee' => ['nullable', 'numeric', 'min:0', 'max:9999999'], 'method' => ['required', 'in:bank_transfer,upi,cheque,other'],
            'reference' => ['required', 'string', 'max:100'], 'paid_on' => ['required', 'date', 'before_or_equal:today'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $s = $service->settle($tenant, $data['fee'] ?? null, $data['method'], $data['reference'], $data['paid_on'], $request->user(), $data['notes'] ?? null);

        return back()->with('success', __('Settled ₹:n to :op.', ['n' => number_format((float) $s->net, 2), 'op' => $tenant->name]));
    }

    public function reports(Request $request): View
    {
        $months = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $subs = SubscriptionPayment::query()->where('status', 'paid')->where('paid_at', '>=', $months->first())->get(['total', 'paid_at'])
            ->groupBy(fn ($p) => $p->paid_at->format('Y-m'))->map->sum('total');
        $bookings = Booking::query()->where('created_at', '>=', $months->first())->get(['created_at', 'status', 'source'])->groupBy(fn ($b) => $b->created_at->format('Y-m'));
        $online = DB::table('payments')->where('method', 'razorpay')->where('amount', '>', 0)->where('paid_at', '>=', $months->first())->get(['amount', 'paid_at'])
            ->groupBy(fn ($p) => substr((string) $p->paid_at, 0, 7))->map->sum('amount'); // portable (no DATE_FORMAT)

        return view('admin.finance.reports', [
            'months' => $months->map(fn ($m) => (object) [
                'label' => $m->format('M Y'), 'key' => $m->format('Y-m'), 'subscriptions' => (float) ($subs[$m->format('Y-m')] ?? 0),
                'bookings' => ($bookings[$m->format('Y-m')] ?? collect())->count(), 'online_bookings' => ($bookings[$m->format('Y-m')] ?? collect())->where('source', 'website')->count(),
                'online' => (float) ($online[$m->format('Y-m')] ?? 0),
            ]),
            'topOperators' => Booking::query()->where('created_at', '>=', now()->subDays(30))->selectRaw('tenant_id, count(*) as n')->groupBy('tenant_id')->orderByDesc('n')->limit(10)->with('tenant:id,uuid,name')->get(),
        ]);
    }
}
