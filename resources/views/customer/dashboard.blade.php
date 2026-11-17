<x-layouts.public :title="__('My account')" noindex :mobile-cta="false">
    <section class="hero hero-sm">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="avatar" style="width:3.5rem;height:3.5rem;font-size:1.2rem">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <div>
                        <h1 class="h3 mb-0">{{ __('Hi, :name', ['name' => \Illuminate\Support\Str::before($user->name, ' ')]) }}</h1>
                        <div class="small text-white-50">{{ $user->phone }}@if ($user->email) · {{ $user->email }}@endif</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-glass btn-sm"><x-icon name="box-arrow-right" />{{ __('Logout') }}</button></form>
            </div>
        </div>
    </section>
    <section class="section pt-5">
        <div class="container" style="max-width: 960px">
            <div class="row g-3 mb-4">
                <div class="col-sm-6"><a href="{{ route('home') }}#quote" class="route-chip h-100"><span class="d-flex align-items-center gap-3"><span class="icon-tile tile-sm"><x-icon name="lightning-charge" /></span>{{ __('Get a fare quote') }}</span><x-icon name="chevron-right" class="arrow" /></a></div>
                <div class="col-sm-6"><a href="{{ route('contact') }}" class="route-chip h-100"><span class="d-flex align-items-center gap-3"><span class="icon-tile tile-sm"><x-icon name="headset" /></span>{{ __('Contact support') }}</span><x-icon name="chevron-right" class="arrow" /></a></div>
            </div>
            <div class="card panel-card">
                <div class="card-header">{{ __('My bookings') }}</div>
                <x-empty-state icon="taxi-front" :title="__('No bookings yet')" :text="__('Your upcoming and past trips will appear here.')">
                    <a href="{{ route('home') }}#quote" class="btn btn-primary mt-3">{{ __('Book your first ride') }}<x-icon name="arrow-right" /></a>
                </x-empty-state>
            </div>
        </div>
    </section>
</x-layouts.public>
