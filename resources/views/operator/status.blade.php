<x-layouts.auth :title="__('Account status')" width="col-md-8 col-lg-6">
    @php $status = $tenant->status->value; @endphp
    <div class="text-center">
        @if ($status === 'pending')
            <i class="bi bi-hourglass-split display-4 text-warning"></i>
            <h1 class="h4 fw-800 mt-3">{{ __('Your business is under verification') }}</h1>
            <p class="text-body-secondary">{{ __('Thanks for registering :name. Our team will verify your details and activate your account, usually within one working day. We will contact you on :phone.', ['name' => $tenant->name, 'phone' => $tenant->phone]) }}</p>
        @elseif ($status === 'suspended')
            <i class="bi bi-pause-circle display-4 text-danger"></i>
            <h1 class="h4 fw-800 mt-3">{{ __('Account suspended') }}</h1>
            <p class="text-body-secondary">{{ __('Access to :name has been suspended.', ['name' => $tenant->name]) }}</p>
            @if ($tenant->suspension_reason)<p class="small">{{ __('Reason') }}: {{ $tenant->suspension_reason }}</p>@endif
        @else
            <i class="bi bi-x-octagon display-4 text-secondary"></i>
            <h1 class="h4 fw-800 mt-3">{{ __('Registration not approved') }}</h1>
            @if ($tenant->suspension_reason)<p class="small">{{ __('Reason') }}: {{ $tenant->suspension_reason }}</p>@endif
        @endif
        <p class="small">{{ __('Questions? Write to') }} <a href="mailto:{{ config('aaochale.support_email') }}">{{ config('aaochale.support_email') }}</a></p>
        <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf
            <button class="btn btn-outline-dark"><i class="bi bi-box-arrow-right me-1"></i>{{ __('Logout') }}</button>
        </form>
    </div>
</x-layouts.auth>
