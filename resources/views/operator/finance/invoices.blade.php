@php use App\Support\Money; @endphp
<x-layouts.panel :title="__('Invoices')" panel="operator">
    <x-page-header :title="__('Invoices')" :subtitle="__('GST invoices and credit notes issued to customers')" />
    <div class="card panel-card">
        <div class="card-body border-bottom">
            <form method="GET" class="row g-2">
                <div class="col-md-6"><div class="input-icon"><x-icon name="search" /><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('Invoice or booking number') }}" aria-label="{{ __('Search') }}"></div></div>
                <div class="col-6 col-md-3"><select name="type" class="form-select" onchange="this.form.submit()" aria-label="{{ __('Type') }}"><option value="">{{ __('All documents') }}</option><option value="invoice" @selected(($filters['type'] ?? '') === 'invoice')>{{ __('Invoices') }}</option><option value="credit_note" @selected(($filters['type'] ?? '') === 'credit_note')>{{ __('Credit notes') }}</option></select></div>
                <div class="col-6 col-md-3"><button class="btn btn-ink w-100"><x-icon name="funnel" />{{ __('Filter') }}</button></div>
            </form>
        </div>
        <div class="table-responsive"><table class="table table-modern align-middle">
            <thead><tr><th>{{ __('Number') }}</th><th>{{ __('Date') }}</th><th>{{ __('Customer') }}</th><th>{{ __('Booking') }}</th><th class="text-end">{{ __('Total') }}</th><th></th></tr></thead>
            <tbody>
                @forelse ($invoices as $i)
                    <tr>
                        <td><a href="{{ route('operator.invoices.show', $i) }}" class="fw-semibold">{{ $i->number }}</a><div>@if ($i->isCreditNote())<span class="badge-soft danger">{{ __('Credit note') }}</span>@elseif ($i->status === 'credited')<span class="badge-soft muted">{{ __('Credited') }}</span>@else<span class="badge-soft ok">{{ __('Issued') }}</span>@endif</div></td>
                        <td class="text-nowrap">{{ $i->invoice_date->format('d M Y') }}</td>
                        <td>{{ $i->buyer['name'] ?? '' }}@if (! empty($i->buyer['gstin']))<div class="small text-muted-2">{{ $i->buyer['gstin'] }}</div>@endif</td>
                        <td><a href="{{ route('operator.bookings.show', $i->booking) }}">{{ $i->booking->booking_number }}</a></td>
                        <td class="text-end fw-bold text-nowrap {{ $i->isCreditNote() ? 'text-danger' : '' }}">{{ $i->isCreditNote() ? '− ' : '' }}{{ Money::format($i->total) }}</td>
                        <td class="text-end"><a href="{{ route('operator.invoices.pdf', $i) }}" class="btn btn-sm btn-light" aria-label="{{ __('Download PDF') }}"><x-icon name="download" /></a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state icon="receipt" :title="__('No invoices yet')" :text="__('Issue an invoice from a completed booking.')" /></td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if ($invoices->hasPages())<div class="card-body border-top">{{ $invoices->links() }}</div>@endif
    </div>
</x-layouts.panel>
