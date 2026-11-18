<x-layouts.driver :title="__('Profile')">
    <div class="text-center mb-3">
        <span class="avatar mx-auto mb-2" style="width:4rem;height:4rem;font-size:1.5rem">{{ mb_strtoupper(mb_substr($driver->name, 0, 1)) }}</span>
        <h1 class="h5 mb-0">{{ $driver->name }}</h1>
        <div class="small text-muted-2">{{ auth()->user()->tenant->name }}@if ($driver->branch) · {{ $driver->branch->name }}@endif</div>
    </div>
    <div class="card panel-card mb-3">
        <div class="list-group list-group-flush list-modern small">
            <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Mobile') }}</span><span>{{ $driver->phone }}</span></div>
            <div class="list-group-item d-flex justify-content-between"><span class="text-muted-2">{{ __('Licence') }}</span><span>{{ $driver->licence_number }} @if ($driver->licence_type)({{ $driver->licence_type }})@endif</span></div>
            <div class="list-group-item d-flex justify-content-between align-items-center"><span class="text-muted-2">{{ __('Licence valid till') }}</span><span>{{ $driver->licence_expiry?->format('d M Y') ?? '—' }} @if ($driver->licence_expiry)<x-expiry-badge :expiry="\App\Support\Expiry::of($driver->licence_expiry)" />@endif</span></div>
        </div>
    </div>
    <div class="card panel-card mb-3">
        <div class="card-header">{{ __('My documents') }}</div>
        <div class="list-group list-group-flush list-modern small">
            @forelse ($driver->documents as $doc)
                <div class="list-group-item d-flex justify-content-between align-items-center"><span><x-icon name="file-earmark-text" class="text-muted-2" /> {{ $docTypes[$doc->type] ?? $doc->type }}</span><span>@if ($doc->expires_on){{ $doc->expires_on->format('d M Y') }} <x-expiry-badge :expiry="$doc->expiry()" />@else — @endif</span></div>
            @empty
                <x-empty-state icon="file-earmark-text" :title="__('No documents uploaded')" :text="__('Ask your operator to upload your licence and ID.')" />
            @endforelse
        </div>
    </div>
    <div class="d-grid gap-2">
        @if (config('aaochale.support_phone_e164'))<a href="tel:{{ config('aaochale.support_phone_e164') }}" class="btn btn-light border"><x-icon name="headset" />{{ __('Help & support') }}</a>@endif
        <form method="POST" action="{{ route('logout') }}" class="d-grid">@csrf<button class="btn btn-outline-danger"><x-icon name="box-arrow-right" />{{ __('Logout') }}</button></form>
    </div>
</x-layouts.driver>
