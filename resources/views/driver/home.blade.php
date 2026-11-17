<x-layouts.mobile :title="__('Driver')">
    <h1 class="h5 fw-800 mb-0">{{ __('Hi, :name', ['name' => $user->name]) }}</h1>
    <div class="small text-body-secondary mb-3">{{ $tenant->name }}</div>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-transparent fw-semibold">{{ __('Assigned trips') }}</div>
        <x-empty-state icon="sign-turn-right" :title="__('No trips assigned')" :text="__('New trips assigned by your operator will appear here.')" />
    </div>
</x-layouts.mobile>
