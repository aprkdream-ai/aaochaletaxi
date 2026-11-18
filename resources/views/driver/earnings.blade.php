@php use App\Support\Money; @endphp
<x-layouts.driver :title="__('Earnings')">
    <h1 class="h5 mb-1">{{ __('Earnings') }}</h1>
    <div class="small text-muted-2 mb-3">{{ $payTypes[$driver->pay_type] ?? '' }}@if ($driver->pay_amount) · {{ $driver->pay_type === 'commission' ? rtrim(rtrim($driver->pay_amount, '0'), '.').'%' : Money::format($driver->pay_amount, 0) }}@endif</div>
    <div class="earn-hero rounded-4 p-3 mb-3 text-white bg-ink">
        <div class="small opacity-75">{{ __(':month so far', ['month' => now()->translatedFormat('F')]) }}</div>
        <div class="display-6 fw-bold">{{ Money::format($month['earned'], 0) }}</div>
        <div class="d-flex gap-3 small mt-2 opacity-75"><span>{{ trans_choice('{1} 1 trip|[0,*] :n trips', $month['trips'], ['n' => $month['trips']]) }}</span><span>{{ __('Allowances :a', ['a' => Money::format($month['allowances'], 0)]) }}</span></div>
    </div>
    <div class="card panel-card mb-3">
        <div class="list-group list-group-flush list-modern">
            <div class="list-group-item d-flex align-items-center gap-3"><span class="stat-ico tone-success" style="width:2.2rem;height:2.2rem"><x-icon name="cash-stack" /></span><span class="me-auto">{{ __('Paid this month') }}</span><b>{{ Money::format($month['paid'], 0) }}</b></div>
            <div class="list-group-item d-flex align-items-center gap-3"><span class="stat-ico tone-warning" style="width:2.2rem;height:2.2rem"><x-icon name="hourglass-split" /></span><span class="me-auto">{{ __('Pending') }}</span><b>{{ Money::format($month['due'], 0) }}</b></div>
            <div class="list-group-item d-flex align-items-center gap-3"><span class="stat-ico tone-info" style="width:2.2rem;height:2.2rem"><x-icon name="calendar-check" /></span><span class="me-auto">{{ __('Last month') }} <span class="small text-muted-2">· {{ trans_choice('{1} 1 trip|[0,*] :n trips', $last['trips'], ['n' => $last['trips']]) }}</span></span><b>{{ Money::format($last['earned'], 0) }}</b></div>
        </div>
    </div>
    <div class="card panel-card mb-3">
        <div class="card-header">{{ __('This month’s trips') }}</div>
        <div class="list-group list-group-flush list-modern small">
            @forelse (array_reverse($month['lines']) as $line)
                <div class="list-group-item d-flex justify-content-between"><span>{{ \Illuminate\Support\Carbon::parse($line['date'])->format('d M') }} · {{ $line['booking'] }}</span><span>{{ Money::format($line['fare'], 0) }}</span></div>
            @empty
                <x-empty-state icon="wallet2" :title="__('No completed trips this month')" />
            @endforelse
        </div>
    </div>
    <div class="card panel-card">
        <div class="card-header">{{ __('Recent payouts') }}</div>
        <div class="list-group list-group-flush list-modern small">
            @forelse ($payouts as $p)
                <div class="list-group-item d-flex justify-content-between"><span>{{ $p->period_from->format('d M') }} – {{ $p->period_to->format('d M') }}</span><span class="fw-semibold text-success">{{ Money::format($p->amount, 0) }}</span></div>
            @empty
                <x-empty-state icon="cash-stack" :title="__('No payouts yet')" />
            @endforelse
        </div>
    </div>
</x-layouts.driver>
