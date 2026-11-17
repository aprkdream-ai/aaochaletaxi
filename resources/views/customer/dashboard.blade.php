<x-layouts.public :title="__('My account')" noindex>
    <section class="section">
        <div class="container" style="max-width: 900px">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <div>
                    <h1 class="h3 fw-800 mb-0">{{ __('Hi, :name', ['name' => $user->name]) }}</h1>
                    <div class="text-body-secondary small">{{ $user->phone }} @if($user->email) · {{ $user->email }} @endif</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-dark btn-sm"><i class="bi bi-box-arrow-right me-1"></i>{{ __('Logout') }}</button></form>
            </div>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent fw-semibold">{{ __('My bookings') }}</div>
                <x-empty-state icon="taxi-front" :title="__('No bookings yet')" :text="__('Need a ride? Request a fare quote and our team will call you back.')">
                    <a href="{{ route('home') }}" class="btn btn-primary btn-sm mt-3">{{ __('Get a fare quote') }}</a>
                </x-empty-state>
            </div>
        </div>
    </section>
</x-layouts.public>
