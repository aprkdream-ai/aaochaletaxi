@php use App\Support\Money; @endphp
<x-layouts.panel :title="__('Subscription')" panel="operator">
    <x-page-header :title="__('Subscription & billing')" />

    @if ($current)
        <div class="card panel-card mb-3 overflow-hidden">
            <div class="card-body p-4" style="background: linear-gradient(120deg, var(--ink), var(--ink-2)); color: rgba(255,255,255,.8)">
                <div class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="small-caps text-white-50 mb-1">{{ __('Current plan') }}</div>
                        <div class="h3 text-white mb-1">{{ $current->plan->name }} <span class="fs-6 fw-normal text-white-50 text-capitalize">· {{ $current->billing_cycle === 'trial' ? __('Free trial') : $current->billing_cycle }}</span></div>
                        <x-status-badge :status="$current->status" />
                    </div>
                    <div class="col-6 col-md-3"><div class="small text-white-50">{{ $current->status->value === 'grace' ? __('Grace until') : __('Valid till') }}</div><div class="fs-5 fw-bold text-white">{{ ($current->status->value === 'grace' ? $current->grace_ends_at : $current->ends_at)?->format('d M Y') }}</div></div>
                    <div class="col-6 col-md-3"><div class="small text-white-50">{{ __('Days left') }}</div><div class="fs-5 fw-bold text-brand">{{ $current->status->value === 'grace' ? max(0, (int) now()->startOfDay()->diffInDays($current->grace_ends_at?->startOfDay(), false)) : $current->daysLeft() }}</div></div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 small">
                    @foreach ([__('Staff users') => [$usage['users'], $current->plan->max_users], __('Vehicles') => [null, $current->plan->max_vehicles], __('Drivers') => [null, $current->plan->max_drivers], __('Branches') => [null, $current->plan->max_branches]] as $label => [$used, $max])
                        <div class="col-6 col-md-3">
                            <div class="d-flex justify-content-between"><span class="text-muted-2">{{ $label }}</span><b>{{ $used !== null ? $used.' / ' : '' }}{{ $max }}</b></div>
                            @if ($used !== null)<div class="progress mt-1" style="height:6px" role="progressbar" aria-valuenow="{{ $used }}" aria-valuemax="{{ $max }}" aria-label="{{ $label }}"><div class="progress-bar bg-warning" style="width: {{ min(100, $max ? $used / $max * 100 : 0) }}%"></div></div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-danger d-flex flex-wrap align-items-center gap-3 rounded-4">
            <x-icon name="exclamation-octagon-fill" class="icon-lg" />
            <div class="me-auto"><div class="fw-bold">{{ __('No active subscription') }}</div><div class="small">{{ __('Your dashboard is paused. All your data is safe and will be available as soon as you renew.') }}</div></div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card panel-card mb-3">
                <div class="card-header">{{ __('Renew or upgrade') }} @if ($onlinePayment)<span class="badge-soft ok"><x-icon name="shield-check" /> {{ __('Secure online payment') }}</span>@endif</div>
                <div class="card-body" x-data="razorpayCheckout(@js(route('operator.checkout.store')))">
                    @if ($onlinePayment)
                        <div class="d-flex justify-content-center mb-3">
                            <div class="seg"><button type="button" :class="{ active: cycle === 'monthly' }" @click="cycle = 'monthly'">{{ __('Monthly') }}</button><button type="button" :class="{ active: cycle === 'yearly' }" @click="cycle = 'yearly'">{{ __('Yearly') }}</button></div>
                        </div>
                    @else
                        <p class="small text-muted-2">{{ __('To renew or change plan, raise a billing request — our team will share payment details (UPI / bank transfer) and activate your plan as soon as payment is received.') }}</p>
                    @endif
                    <div class="alert alert-danger small py-2" x-show="error" x-text="error" x-cloak></div>
                    <div class="row g-2">
                        @foreach ($plans as $p)
                            <div class="col-md-4">
                                <div @class(['p-3 rounded-4 border h-100 d-flex flex-column', 'border-warning' => $current?->plan_id === $p->id])>
                                    <div class="fw-bold">{{ $p->name }} @if ($current?->plan_id === $p->id)<span class="badge-soft warn">{{ __('Current') }}</span>@endif</div>
                                    <div class="fs-5 fw-800">
                                        <span x-show="cycle === 'monthly'">{{ Money::format($p->price_monthly, 0) }}<span class="small fw-normal text-muted-2">/{{ __('mo') }}</span></span>
                                        <span x-show="cycle === 'yearly'" x-cloak>{{ Money::format($p->price_yearly, 0) }}<span class="small fw-normal text-muted-2">/{{ __('yr') }}</span></span>
                                    </div>
                                    <div class="small text-muted-2 mb-2">{{ __(':v vehicles · :d drivers', ['v' => $p->max_vehicles, 'd' => $p->max_drivers]) }}</div>
                                    @if ($onlinePayment)
                                        <button type="button" class="btn btn-sm {{ $current?->plan_id === $p->id ? 'btn-primary' : 'btn-ink' }} w-100 mt-auto" :disabled="busy" @click="pay({{ $p->id }})">
                                            <span x-show="busy === {{ $p->id }}" class="spinner-border spinner-border-sm" x-cloak></span>{{ $current?->plan_id === $p->id ? __('Renew & pay') : __('Choose & pay') }}
                                        </button>
                                    @else
                                        <a href="{{ route('operator.support.create', ['category' => 'billing', 'subject' => __('Request: :plan plan', ['plan' => $p->name])]) }}" class="btn btn-sm {{ $current?->plan_id === $p->id ? 'btn-primary' : 'btn-light border' }} w-100 mt-auto">{{ $current?->plan_id === $p->id ? __('Renew') : __('Choose') }}</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="small text-muted-2 mt-2">{{ __('Prices exclusive of GST (:p%).', ['p' => rtrim(rtrim($gstPercent, '0'), '.')]) }} @if ($onlinePayment){{ __('UPI, cards, net banking & wallets accepted. Paying while a paid plan is active extends it from its end date.') }}@endif <a href="{{ route('partner') }}#pricing" target="_blank">{{ __('Compare plans') }}</a></div>
                    <form method="POST" x-ref="verify" class="d-none">@csrf<input type="hidden" name="razorpay_order_id"><input type="hidden" name="razorpay_payment_id"><input type="hidden" name="razorpay_signature"></form>
                </div>
            </div>
            <div class="card panel-card">
                <div class="card-header">{{ __('Invoices') }}</div>
                <div class="table-responsive">
                    <table class="table table-modern align-middle small">
                        <thead><tr><th>{{ __('Invoice') }}</th><th>{{ __('Date') }}</th><th class="text-end">{{ __('Amount') }}</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($payments as $p)
                                <tr><td class="fw-semibold text-nowrap">{{ $p->invoice_number }}</td><td class="text-nowrap">{{ $p->paid_at?->format('d M Y') }}</td><td class="text-end fw-bold text-nowrap">{{ Money::format($p->total) }}</td>
                                    <td class="text-end"><a href="{{ route('operator.subscription.invoices.show', $p) }}" target="_blank" class="btn btn-sm btn-light" aria-label="{{ __('Invoice') }}"><x-icon name="download" /><span class="d-none d-sm-inline">{{ __('Invoice') }}</span></a></td></tr>
                            @empty
                                <tr><td colspan="4"><x-empty-state icon="receipt" :title="__('No invoices yet')" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card panel-card">
                <div class="card-header">{{ __('History') }}</div>
                <div class="list-group list-group-flush list-modern small">
                    @foreach ($history as $s)
                        <div @class(['list-group-item d-flex justify-content-between align-items-center gap-2', 'opacity-50' => $s->superseded_by])>
                            <div><div class="fw-semibold">{{ $s->plan->name }} <span class="text-muted-2 fw-normal text-capitalize">· {{ $s->billing_cycle }}</span></div><div class="text-muted-2">{{ $s->starts_at->format('d M Y') }} – {{ $s->ends_at->format('d M Y') }}</div></div>
                            <x-status-badge :status="$s->status" />
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</x-layouts.panel>
