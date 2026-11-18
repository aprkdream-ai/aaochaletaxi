@php use App\Support\Money; $max = max(1, $months->max('subscriptions')); @endphp
<x-layouts.panel :title="__('Platform reports')" panel="admin">
    <x-page-header :title="__('Platform reports')" :subtitle="__('Last 12 months')" />
    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Subscription revenue (12 m)')" :value="Money::format($months->sum('subscriptions'), 0)" icon="credit-card" color="success" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Bookings (12 m)')" :value="number_format($months->sum('bookings'))" icon="journal-bookmark" color="primary" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Online bookings (12 m)')" :value="number_format($months->sum('online_bookings'))" icon="globe2" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Online collections (12 m)')" :value="Money::format($months->sum('online'), 0)" icon="bank" color="warning" /></div>
    </div>
    <div class="row g-3">
        <div class="col-lg-8"><div class="card panel-card"><div class="card-header">{{ __('By month') }}</div><div class="table-responsive"><table class="table table-modern align-middle small">
            <thead><tr><th>{{ __('Month') }}</th><th>{{ __('Subscriptions') }}</th><th class="text-end">{{ __('Bookings') }}</th><th class="text-end">{{ __('Online') }}</th><th class="text-end">{{ __('Online collections') }}</th></tr></thead>
            <tbody>
                @foreach ($months->reverse() as $m)
                    <tr><td class="fw-semibold">{{ $m->label }}</td><td style="min-width:180px"><div class="d-flex align-items-center gap-2"><div class="progress flex-fill" style="height:8px" role="progressbar" aria-label="{{ $m->label }}" aria-valuenow="{{ $m->subscriptions }}" aria-valuemax="{{ $max }}"><div class="progress-bar bg-success" style="width: {{ $m->subscriptions / $max * 100 }}%"></div></div><span class="text-nowrap">{{ Money::format($m->subscriptions, 0) }}</span></div></td><td class="text-end">{{ $m->bookings }}</td><td class="text-end">{{ $m->online_bookings }}</td><td class="text-end">{{ Money::format($m->online, 0) }}</td></tr>
                @endforeach
            </tbody>
        </table></div></div></div>
        <div class="col-lg-4"><div class="card panel-card"><div class="card-header">{{ __('Most active operators (30 days)') }}</div><div class="list-group list-group-flush list-modern small">
            @forelse ($topOperators as $t)<a href="{{ route('admin.tenants.show', $t->tenant) }}" class="list-group-item list-group-item-action d-flex justify-content-between"><span>{{ $t->tenant->name }}</span><b>{{ $t->n }}</b></a>
            @empty<x-empty-state icon="buildings" :title="__('No bookings yet')" />@endforelse
        </div></div></div>
    </div>
</x-layouts.panel>
