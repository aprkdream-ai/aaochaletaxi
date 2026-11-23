@php use App\Support\Money; @endphp
<x-layouts.panel :title="__('Marketplace requests')" panel="operator">
    <x-page-header :title="__('Marketplace requests')" :subtitle="__('Ride requests from AaoChaleTaxi marketplace — the first operator to accept gets the booking')" />
    @error('lead')<div class="alert alert-danger small">{{ $message }}</div>@enderror
    <div class="card panel-card"><div class="list-group list-group-flush list-modern">
        @forelse ($offers as $o)
            @php $l = $o->lead; $open = $o->status === 'pending' && $l->isOpen(); @endphp
            <div class="list-group-item">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <span class="date-tile"><b>{{ $l->pickup_at->format('d') }}</b><small>{{ $l->pickup_at->format('M') }}</small></span>
                    <div class="me-auto min-w-0">
                        <div class="fw-semibold">{{ $l->from_city }}@if ($l->to_city) → {{ $l->to_city }}@endif <span class="text-muted-2 fw-normal">· {{ $l->type->label() }} · {{ $l->category?->name }}</span></div>
                        <div class="small text-muted-2">{{ $l->pickup_at->format('D, h:i A') }}@if ($l->distance_km) · {{ __('about :km km', ['km' => number_format((float) $l->distance_km)]) }}@endif · {{ trans_choice('{1} 1 passenger|[2,*] :n passengers', $l->passengers, ['n' => $l->passengers]) }}@if ($o->estimate) · {{ __('your fare :f', ['f' => Money::format($o->estimate, 0)]) }}@endif</div>
                        @if ($open)<div class="small text-warning">{{ __('Open until :t — customer details are shared after you accept.', ['t' => $l->expires_at->format('h:i A')]) }}</div>@endif
                    </div>
                    @if ($open)
                        <form method="POST" action="{{ route('operator.leads.accept', $o) }}" data-confirm="{{ __('Accept this request and create a booking?') }}" data-confirm-tone="primary">@csrf<button class="btn btn-sm btn-success"><x-icon name="check2" />{{ __('Accept') }}</button></form>
                        <form method="POST" action="{{ route('operator.leads.decline', $o) }}">@csrf<button class="btn btn-sm btn-light border">{{ __('Decline') }}</button></form>
                    @elseif ($o->status === 'accepted' && $l->booking)
                        <a href="{{ route('operator.bookings.show', $l->booking_id) }}" class="btn btn-sm btn-ink">{{ $l->booking->booking_number }}</a>
                    @else
                        <span class="badge-soft muted">{{ ['declined' => __('Declined'), 'lost' => __('Taken by another operator'), 'expired' => __('Expired'), 'pending' => __('Expired')][$o->status] ?? $o->status }}</span>
                    @endif
                </div>
            </div>
        @empty
            <x-empty-state icon="megaphone" :title="__('No requests yet')" :text="__('Turn on the marketplace and add the cities you serve in Business profile to receive ride requests.')" />
        @endforelse
    </div>
    @if ($offers->hasPages())<div class="card-body border-top">{{ $offers->links() }}</div>@endif
    </div>
</x-layouts.panel>
