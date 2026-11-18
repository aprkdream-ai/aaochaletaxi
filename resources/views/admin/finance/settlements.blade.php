@php use App\Support\Money; @endphp
<x-layouts.panel :title="__('Operator settlements')" panel="admin">
    <x-page-header :title="__('Operator settlements')" :subtitle="__('Advances collected online on the platform Razorpay account, to be paid out to operators (default fee :p%)', ['p' => $feePercent])" />
    @error('settlement')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    @error('fee')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    <div class="card panel-card mb-3"><div class="card-header">{{ __('Outstanding') }}</div><div class="table-responsive"><table class="table table-modern align-middle">
        <thead><tr><th>{{ __('Operator') }}</th><th class="text-end">{{ __('Payments') }}</th><th class="text-end">{{ __('Collected') }}</th><th>{{ __('Oldest') }}</th><th>{{ __('Pay out') }}</th></tr></thead>
        <tbody>
            @forelse ($outstanding as $o)
                <tr>
                    <td><a href="{{ route('admin.tenants.show', $o->uuid) }}" class="fw-semibold">{{ $o->name }}</a></td>
                    <td class="text-end">{{ $o->payments_count }}</td>
                    <td class="text-end fw-bold">{{ Money::format($o->gross) }}</td>
                    <td class="small text-muted-2">{{ \Illuminate\Support\Carbon::parse($o->oldest)->diffForHumans() }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.finance.settle', $o->uuid) }}" class="d-flex flex-wrap gap-1" data-confirm="{{ __('Record this payout? All outstanding online payments of this operator will be marked settled.') }}">@csrf
                            <input type="number" step="0.01" min="0" name="fee" class="form-control form-control-sm" style="width:100px" placeholder="{{ __('Fee') }} {{ $feePercent }}%" aria-label="{{ __('Fee') }}">
                            <select name="method" class="form-select form-select-sm" style="width:110px" aria-label="{{ __('Method') }}"><option value="bank_transfer">NEFT/IMPS</option><option value="upi">UPI</option><option value="cheque">{{ __('Cheque') }}</option></select>
                            <input type="text" name="reference" class="form-control form-control-sm" style="width:140px" placeholder="UTR" required aria-label="UTR">
                            <input type="date" name="paid_on" value="{{ now()->toDateString() }}" class="form-control form-control-sm" style="width:140px" aria-label="{{ __('Paid on') }}">
                            <button class="btn btn-sm btn-primary">{{ __('Settle') }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-empty-state icon="bank" :title="__('Nothing to settle')" :text="__('Online advances will appear here.')" /></td></tr>
            @endforelse
        </tbody>
    </table></div></div>
    <div class="card panel-card"><div class="card-header">{{ __('History') }}</div><div class="table-responsive"><table class="table table-modern small">
        <thead><tr><th>{{ __('Paid on') }}</th><th>{{ __('Operator') }}</th><th class="text-end">{{ __('Gross') }}</th><th class="text-end">{{ __('Fee') }}</th><th class="text-end">{{ __('Net paid') }}</th><th>{{ __('Reference') }}</th></tr></thead>
        <tbody>
            @forelse ($history as $h)<tr><td>{{ $h->paid_on->format('d M Y') }}</td><td>{{ $h->tenant->name }}</td><td class="text-end">{{ Money::format($h->gross) }}</td><td class="text-end">{{ Money::format($h->fee) }}</td><td class="text-end fw-bold">{{ Money::format($h->net) }}</td><td>{{ strtoupper($h->method) }} {{ $h->reference }}</td></tr>
            @empty<tr><td colspan="6"><x-empty-state icon="clock-history" :title="__('No settlements yet')" /></td></tr>@endforelse
        </tbody>
    </table></div></div>
</x-layouts.panel>
