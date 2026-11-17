<x-layouts.panel :title="__('Dashboard')" panel="operator">
    <x-page-header :title="__('Welcome, :name', ['name' => auth()->user()->name])" :subtitle="$tenant->name" />

    @if ($subscription && $subscription->status->value === 'trialing')
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-gift"></i>
            <div>{{ __('You are on a free trial of the :plan plan — :days days left.', ['plan' => $subscription->plan->name, 'days' => $subscription->daysLeft()]) }}</div>
        </div>
    @elseif (! $subscription)
        <div class="alert alert-danger"><i class="bi bi-exclamation-triangle me-2"></i>{{ __('Your subscription has expired. Please contact support to renew.') }}</div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Plan')" :value="$subscription?->plan->name ?? '—'" icon="award" color="primary" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Valid till')" :value="$subscription?->ends_at->format('d M Y') ?? '—'" icon="calendar-check" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Team members')" :value="$staffCount" icon="people" color="success" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Vehicle limit')" :value="$subscription?->plan->max_vehicles ?? '—'" icon="truck-front" color="secondary" /></div>
    </div>

    <div class="card">
        <div class="card-header bg-transparent fw-semibold">{{ __('Your booking page') }}</div>
        <div class="card-body">
            <p class="mb-2 small text-body-secondary">{{ __('Customers will be able to book directly with you at:') }}</p>
            <code class="fs-6">{{ url('/o/'.$tenant->slug) }}</code>
        </div>
    </div>
</x-layouts.panel>
