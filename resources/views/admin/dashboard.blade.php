<x-layouts.panel :title="__('Dashboard')" panel="admin">
    <x-page-header :title="__('Platform overview')" :subtitle="now()->format('l, d M Y')" />

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Active operators')" :value="$stats['operators_active']" icon="buildings" color="success" :href="route('admin.tenants.index', ['status' => 'active'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Pending approval')" :value="$stats['operators_pending']" icon="hourglass-split" color="warning" :href="route('admin.tenants.index', ['status' => 'pending'])" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('On trial / paid')" :value="$stats['trials'].' / '.$stats['paid']" icon="credit-card" color="info" /></div>
        <div class="col-6 col-xl-3"><x-stat-card :label="__('Customers')" :value="$stats['customers']" icon="people" color="primary" /></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">{{ __('Awaiting approval') }}</span>
                    <a href="{{ route('admin.tenants.index', ['status' => 'pending']) }}" class="small">{{ __('View all') }}</a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($pendingTenants as $t)
                        <a href="{{ route('admin.tenants.show', $t) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div><div class="fw-semibold">{{ $t->name }}</div><div class="small text-body-secondary">{{ $t->city }} · {{ $t->created_at->diffForHumans() }}</div></div>
                            <i class="bi bi-chevron-right text-body-secondary"></i>
                        </a>
                    @empty
                        <x-empty-state icon="check2-circle" :title="__('No pending operators')" />
                    @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header bg-transparent fw-semibold">{{ __('Recent activity') }}</div>
                <div class="list-group list-group-flush">
                    @forelse ($recentActivity as $log)
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between"><code class="small">{{ $log->event }}</code><span class="small text-body-secondary">{{ $log->created_at->diffForHumans() }}</span></div>
                            <div class="small text-body-secondary">{{ $log->user?->name ?? __('System') }} @if($log->tenant) · {{ $log->tenant->name }} @endif</div>
                        </div>
                    @empty
                        <x-empty-state icon="activity" :title="__('No activity yet')" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    @if ($stats['enquiries'] > 0)
        <div class="alert alert-info mt-4 mb-0"><i class="bi bi-envelope me-2"></i>{{ trans_choice('{1} :n new website enquiry.|[2,*] :n new website enquiries.', $stats['enquiries'], ['n' => $stats['enquiries']]) }}</div>
    @endif
</x-layouts.panel>
