<x-layouts.panel :title="__('Dashboard')" panel="admin">
    <x-page-header :title="__('Good :part, :name', ['part' => now()->hour < 12 ? __('morning') : (now()->hour < 17 ? __('afternoon') : __('evening')), 'name' => \Illuminate\Support\Str::before(auth()->user()->name, ' ')])" :subtitle="now()->format('l, d M Y')">
        <a href="{{ route('admin.tenants.index', ['status' => 'pending']) }}" class="btn btn-primary"><x-icon name="hourglass-split" />{{ __('Review pending') }}</a>
    </x-page-header>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Active operators')" :value="$stats['operators_active']" icon="buildings" color="success" :href="route('admin.tenants.index', ['status' => 'active'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Pending approval')" :value="$stats['operators_pending']" icon="hourglass-split" color="warning" :href="route('admin.tenants.index', ['status' => 'pending'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Trial / Paid')" :value="$stats['trials'].' / '.$stats['paid']" icon="credit-card" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Customers')" :value="$stats['customers']" icon="people" color="primary" :hint="trans_choice('{0} No new enquiries|{1} :n new enquiry|[2,*] :n new enquiries', $stats['enquiries'], ['n' => $stats['enquiries']])" /></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Revenue this month')" :value="\App\Support\Money::format($stats['revenue_month'], 0)" icon="cash-stack" color="success" :href="route('admin.subscriptions.index')" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Expiring in 7 days')" :value="$stats['expiring']" icon="alarm" color="warning" :href="route('admin.subscriptions.index', ['expiring' => 1])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Open tickets')" :value="$stats['tickets']" icon="life-preserver" color="info" :href="route('admin.support.index')" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('KYC docs to review')" :value="$stats['kyc_pending']" icon="patch-check" color="danger" /></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card panel-card h-100">
                <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="hourglass-split" class="text-warning" />{{ __('Awaiting approval') }}</span><a href="{{ route('admin.tenants.index', ['status' => 'pending']) }}" class="small fw-semibold">{{ __('View all') }}</a></div>
                <div class="list-group list-group-flush list-modern">
                    @forelse ($pendingTenants as $t)
                        <a href="{{ route('admin.tenants.show', $t) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-3">
                            <span class="avatar">{{ mb_strtoupper(mb_substr($t->name, 0, 2)) }}</span>
                            <div class="min-w-0 flex-grow-1"><div class="fw-semibold text-truncate">{{ $t->name }}</div><div class="small text-muted-2">{{ $t->city }} · {{ $t->created_at->diffForHumans() }}</div></div>
                            <x-icon name="chevron-right" class="text-muted-2" />
                        </a>
                    @empty
                        <x-empty-state icon="check2-circle" :title="__('All caught up')" :text="__('No operators are waiting for approval.')" />
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card panel-card h-100">
                <div class="card-header"><span class="d-flex align-items-center gap-2"><x-icon name="activity" class="text-info" />{{ __('Recent activity') }}</span></div>
                <div class="list-group list-group-flush list-modern">
                    @forelse ($recentActivity as $log)
                        <div class="list-group-item d-flex gap-3">
                            <span class="stat-ico tone-secondary" style="width:2rem;height:2rem;font-size:.9rem"><x-icon :name="\App\Support\AuditPresenter::icon($log->event)" /></span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="d-flex justify-content-between gap-2"><span class="fw-semibold small">{{ \App\Support\AuditPresenter::label($log->event) }}</span><span class="small text-muted-2 text-nowrap">{{ $log->created_at->diffForHumans() }}</span></div>
                                <div class="small text-muted-2 text-truncate">{{ $log->user?->name ?? __('System') }}@if ($log->tenant) · {{ $log->tenant->name }}@endif</div>
                            </div>
                        </div>
                    @empty
                        <x-empty-state icon="activity" :title="__('No activity yet')" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.panel>
