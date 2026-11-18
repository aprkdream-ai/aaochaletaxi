<x-layouts.driver :title="__('Driver')">
    <div class="card panel-card">
        <x-empty-state icon="person-badge" :title="__('Your driver profile is not set up yet')" :text="__(':operator has not linked a driver profile to your login. Please contact them.', ['operator' => auth()->user()->tenant->name])" />
    </div>
    <form method="POST" action="{{ route('logout') }}" class="d-grid mt-3">@csrf<button class="btn btn-outline-danger"><x-icon name="box-arrow-right" />{{ __('Logout') }}</button></form>
</x-layouts.driver>
