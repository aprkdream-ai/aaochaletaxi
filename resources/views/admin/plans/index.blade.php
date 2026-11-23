<x-layouts.panel :title="__('Plans')" panel="admin">
    <x-page-header :title="__('Subscription plans')" :subtitle="__('Prices, limits and features shown on the pricing page')">
        <a href="{{ route('admin.plans.create') }}" class="btn btn-primary"><x-icon name="plus-lg" />{{ __('New plan') }}</a>
    </x-page-header>
    <div class="row g-3">
        @foreach ($plans as $plan)
            <div class="col-md-6 col-xl-4">
                <div class="card panel-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div><h2 class="h5 mb-0">{{ $plan->name }}</h2><code class="small">{{ $plan->code }}</code></div>
                            <span class="badge-soft {{ $plan->is_active ? 'ok' : 'muted' }}">{{ $plan->is_active ? __('Active') : __('Hidden') }}</span>
                        </div>
                        <div class="d-flex align-items-baseline gap-2 mb-3"><span class="fs-3 fw-800">{{ \App\Support\Money::format($plan->price_monthly, 0) }}</span><span class="text-muted-2 small">/{{ __('month') }} · {{ \App\Support\Money::format($plan->price_yearly, 0) }}/{{ __('year') }}</span></div>
                        <div class="small text-muted-2 mt-n2 mb-3">{{ __('Add-on (Taxi + Fleet): :m/month · :y/year', ['m' => \App\Support\Money::format($plan->addon_price_monthly, 0), 'y' => \App\Support\Money::format($plan->addon_price_yearly, 0)]) }}</div>
                        <div class="row g-2 small mb-3">
                            @foreach ([__('Vehicles') => $plan->max_vehicles, __('Drivers') => $plan->max_drivers, __('Branches') => $plan->max_branches, __('Users') => $plan->max_users] as $l => $v)
                                <div class="col-6"><div class="p-2 rounded-3 bg-surface-2"><span class="text-muted-2">{{ $l }}</span> <b class="float-end">{{ $v }}</b></div></div>
                            @endforeach
                        </div>
                        <div class="small text-muted-2 mb-3">{{ trans_choice('{0} No active subscribers|{1} :n active subscriber|[2,*] :n active subscribers', $plan->active_count, ['n' => $plan->active_count]) }} · {{ __(':d-day trial', ['d' => $plan->trial_days]) }}</div>
                        <a href="{{ route('admin.plans.edit', $plan) }}" class="btn btn-light border w-100"><x-icon name="pencil" />{{ __('Edit plan') }}</a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.panel>
