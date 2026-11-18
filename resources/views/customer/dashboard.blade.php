@php use App\Support\Money; @endphp
<x-layouts.public :title="__('My account')" noindex :mobile-cta="false">
    <section class="hero hero-sm">
        <div class="container" style="max-width: 1000px">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar" style="width:3.5rem;height:3.5rem;font-size:1.2rem">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <div><h1 class="h3 mb-0">{{ __('Hi, :name', ['name' => \Illuminate\Support\Str::before($user->name, ' ')]) }}</h1><div class="small text-white-50">{{ $user->phone }}@if ($user->email) · {{ $user->email }}@endif</div></div>
                </div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-glass btn-sm"><x-icon name="box-arrow-right" />{{ __('Logout') }}</button></form>
            </div>
        </div>
    </section>
    <section class="section pt-5">
        <div class="container" style="max-width: 1000px">
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card panel-card">
                        <div class="card-header">{{ __('My bookings') }}</div>
                        <div class="list-group list-group-flush list-modern">
                            @forelse ($bookings as $b)
                                <a href="{{ route('booking.track', $b->public_token) }}" class="list-group-item list-group-item-action py-3">
                                    <div class="d-flex justify-content-between gap-2">
                                        <div class="min-w-0"><div class="fw-semibold text-truncate">{{ $b->pickup_address }}@if ($b->drop_address) → {{ $b->drop_address }}@endif</div>
                                            <div class="small text-muted-2">{{ $b->pickup_at->format('d M Y, h:i A') }} · {{ $b->tenant->name }} · {{ $b->booking_number }}</div></div>
                                        <div class="text-end"><div class="fw-bold">{{ ($b->final_total ?? $b->quoted_total) ? Money::format($b->final_total ?? $b->quoted_total, 0) : '—' }}</div><x-status-badge :status="$b->status" /></div>
                                    </div>
                                </a>
                            @empty
                                <x-empty-state icon="taxi-front" :title="__('No bookings yet')" :text="__('Your upcoming and past trips will appear here.')">
                                    <a href="{{ route('home') }}#quote" class="btn btn-primary mt-3">{{ __('Book your first ride') }}<x-icon name="arrow-right" /></a>
                                </x-empty-state>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <form method="POST" action="{{ route('account.profile.update') }}" class="card panel-card" data-loading novalidate>
                        @csrf @method('PUT')
                        <div class="card-header">{{ __('Profile') }}</div>
                        <div class="card-body">
                            <x-input name="name" :label="__('Name')" :value="$user->name" required />
                            <x-input name="email" type="email" :label="__('Email')" :value="$user->email" />
                            <x-input name="password" type="password" :label="__('New password (optional)')" autocomplete="new-password" />
                            <x-input name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" />
                            <button class="btn btn-ink w-100">{{ __('Save') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>
</x-layouts.public>
