<x-layouts.public :title="__('Your ride request')" :description="__('Status of your ride request')">
    <section class="section"><div class="container" style="max-width:720px">
        <div class="card panel-card"><div class="card-body p-4">
            <div class="small text-muted-2 mb-1">{{ $lead->type->label() }} · {{ $lead->category?->name }}</div>
            <h1 class="h4 mb-1">{{ $lead->from_city }}@if ($lead->to_city) → {{ $lead->to_city }}@endif</h1>
            <div class="text-muted-2 mb-4">{{ $lead->pickup_at->format('D, d M Y · h:i A') }}</div>
            @if ($lead->status === 'accepted' && $lead->acceptedTenant)
                <div class="alert alert-success d-flex gap-2"><x-icon name="check2-circle" class="mt-1" /><div><div class="fw-bold">{{ __(':op accepted your request', ['op' => $lead->acceptedTenant->name]) }}</div><div class="small">{{ __('They will call you to confirm the trip.') }}</div></div></div>
                <div class="d-flex flex-wrap gap-2">
                    @if ($lead->booking)<a href="{{ route('booking.track', $lead->booking->public_token) }}" class="btn btn-primary"><x-icon name="geo-alt" />{{ __('Track booking :no', ['no' => $lead->booking->booking_number]) }}</a>@endif
                    <a href="tel:+91{{ $lead->acceptedTenant->phone }}" class="btn btn-light border"><x-icon name="telephone" />{{ $lead->acceptedTenant->phone }}</a>
                </div>
            @elseif ($lead->isOpen())
                <div class="alert alert-info d-flex gap-2" role="status"><x-icon name="hourglass-split" class="mt-1" /><div><div class="fw-bold">{{ __('Waiting for an operator to accept') }}</div><div class="small">{{ __('Sent to :n operators · open until :t', ['n' => $offered, 't' => $lead->expires_at->format('h:i A')]) }}</div></div></div>
                <p class="small text-muted-2 mb-0">{{ __('Keep this page — it updates when you reload. We also notify you by SMS when a booking is created.') }}</p>
            @else
                <div class="alert alert-warning">{{ __('No operator accepted in time. Please try again or book directly with an operator.') }}</div>
                <a href="{{ route('marketplace.search') }}" class="btn btn-primary">{{ __('Search again') }}</a>
            @endif
        </div></div>
    </div></section>
</x-layouts.public>
