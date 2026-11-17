<x-layouts.auth :title="__('Account status')">
    @php $status = $tenant->status->value; @endphp
    <div class="text-center">
        @if ($status === 'pending')
            <span class="icon-tile mb-3" style="width:4rem;height:4rem;font-size:1.8rem"><x-icon name="hourglass-split" /></span>
            <h1 class="h3">{{ __('We’re verifying your business') }}</h1>
            <p class="text-muted-2">{{ __('Thanks for registering :name. Our team will verify your details and activate your account, usually within one working day. We will contact you on :phone.', ['name' => $tenant->name, 'phone' => $tenant->phone]) }}</p>
            <ol class="text-start small list-unstyled d-grid gap-2 my-4 p-3 rounded-4 bg-surface-2">
                <li class="d-flex gap-2"><x-icon name="check-circle-fill" class="text-success mt-1" />{{ __('Registration received') }}</li>
                <li class="d-flex gap-2"><x-icon name="arrow-repeat" class="text-warning mt-1" />{{ __('KYC & business verification') }}</li>
                <li class="d-flex gap-2"><x-icon name="circle" class="text-muted-2 mt-1" />{{ __('Account activated — start your free trial') }}</li>
            </ol>
        @elseif ($status === 'suspended')
            <span class="icon-tile mb-3 tone-danger" style="width:4rem;height:4rem;font-size:1.8rem"><x-icon name="pause-circle" /></span>
            <h1 class="h3">{{ __('Account suspended') }}</h1>
            <p class="text-muted-2">{{ __('Access to :name has been suspended. Your data is safe.', ['name' => $tenant->name]) }}</p>
            @if ($tenant->suspension_reason)<p class="small"><strong>{{ __('Reason') }}:</strong> {{ $tenant->suspension_reason }}</p>@endif
        @else
            <span class="icon-tile mb-3 tone-secondary" style="width:4rem;height:4rem;font-size:1.8rem"><x-icon name="x-octagon" /></span>
            <h1 class="h3">{{ __('Registration not approved') }}</h1>
            @if ($tenant->suspension_reason)<p class="small"><strong>{{ __('Reason') }}:</strong> {{ $tenant->suspension_reason }}</p>@endif
        @endif
        <p class="small">{{ __('Questions? Write to') }} <a href="mailto:{{ config('aaochale.support_email') }}">{{ config('aaochale.support_email') }}</a></p>
        <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf
            <button class="btn btn-light border"><x-icon name="box-arrow-right" />{{ __('Logout') }}</button>
        </form>
    </div>
</x-layouts.auth>
