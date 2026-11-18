<x-layouts.panel :title="__('Subscriptions')" panel="admin">
    <x-page-header :title="__('Subscriptions')" :subtitle="__('Current subscription of every operator')" />
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-4"><x-stat-card :label="__('Collected this month (incl. GST)')" :value="\App\Support\Money::format($revenueMonth, 0)" icon="cash-stack" color="success" /></div>
        <div class="col-sm-6 col-xl-4"><x-stat-card :label="__('Expiring in 7 days')" :value="\App\Models\Subscription::query()->whereNull('superseded_by')->whereIn('status', ['trialing', 'active'])->whereBetween('ends_at', [now(), now()->addDays(7)])->count()" icon="alarm" color="warning" :href="route('admin.subscriptions.index', ['expiring' => 1])" /></div>
        <div class="col-sm-6 col-xl-4"><x-stat-card :label="__('In grace period')" :value="\App\Models\Subscription::query()->whereNull('superseded_by')->where('status', 'grace')->count()" icon="hourglass-bottom" color="danger" :href="route('admin.subscriptions.index', ['status' => 'grace'])" /></div>
    </div>
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card panel-card">
                <div class="card-body border-bottom">
                    <form method="GET" class="row g-2">
                        <div class="col-6 col-md-4"><select name="status" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Status') }}"><option value="">{{ __('All statuses') }}</option>@foreach (\App\Enums\SubscriptionStatus::cases() as $s)<option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>@endforeach</select></div>
                        <div class="col-6 col-md-4"><select name="plan" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Plan') }}"><option value="">{{ __('All plans') }}</option>@foreach ($plans as $p)<option value="{{ $p->id }}" @selected((int) ($filters['plan'] ?? 0) === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
                        <div class="col-md-4 d-flex gap-2"><label class="btn btn-light border flex-fill mb-0"><input type="checkbox" name="expiring" value="1" class="form-check-input me-1" onchange="this.form.submit()" @checked($filters['expiring'] ?? false)>{{ __('Expiring soon') }}</label>@if (array_filter($filters))<a href="{{ route('admin.subscriptions.index') }}" class="btn btn-light" aria-label="{{ __('Reset') }}"><x-icon name="x-lg" /></a>@endif</div>
                    </form>
                </div>
                <div class="table-responsive">
                    <table class="table table-modern align-middle">
                        <thead><tr><th>{{ __('Operator') }}</th><th>{{ __('Plan') }}</th><th>{{ __('Ends') }}</th><th>{{ __('Status') }}</th></tr></thead>
                        <tbody>
                            @forelse ($subscriptions as $s)
                                <tr>
                                    <td><a href="{{ route('admin.tenants.show', $s->tenant) }}#subscription" class="fw-semibold text-reset">{{ $s->tenant->name }}</a><div class="small text-muted-2">{{ $s->tenant->city }}</div></td>
                                    <td>{{ $s->plan->name }}<div class="small text-muted-2 text-capitalize">{{ $s->billing_cycle }}</div></td>
                                    <td class="text-nowrap">{{ $s->ends_at->format('d M Y') }}<div class="small {{ $s->ends_at->isPast() ? 'text-danger' : 'text-muted-2' }}">{{ $s->ends_at->diffForHumans() }}</div></td>
                                    <td><x-status-badge :status="$s->status" /></td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-empty-state icon="credit-card" :title="__('No subscriptions found')" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($subscriptions->hasPages())<div class="card-body border-top">{{ $subscriptions->links() }}</div>@endif
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card panel-card">
                <div class="card-header">{{ __('Recent payments') }}</div>
                <div class="list-group list-group-flush list-modern">
                    @forelse ($payments as $p)
                        <a href="{{ route('admin.invoices.show', $p) }}" class="list-group-item list-group-item-action d-flex justify-content-between gap-2" target="_blank">
                            <div class="min-w-0"><div class="fw-semibold text-truncate">{{ $p->tenant->name }}</div><div class="small text-muted-2">{{ $p->invoice_number }} · {{ $p->paid_at?->format('d M') }}</div></div>
                            <div class="fw-bold text-nowrap">{{ \App\Support\Money::format($p->total, 0) }}</div>
                        </a>
                    @empty
                        <x-empty-state icon="receipt" :title="__('No payments yet')" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.panel>
