<x-layouts.mobile :title="__('Driver')">
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="avatar" style="width:3rem;height:3rem">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
        <div><h1 class="h5 mb-0">{{ __('Hi, :name', ['name' => \Illuminate\Support\Str::before($user->name, ' ')]) }}</h1><div class="small text-muted-2">{{ $tenant->name }}</div></div>
    </div>
    <div class="row g-2 mb-3">
        <div class="col-6"><x-stat-card :label="__('Today’s trips')" value="0" icon="sign-turn-right" color="primary" /></div>
        <div class="col-6"><x-stat-card :label="__('Completed')" value="0" icon="check2-circle" color="success" /></div>
    </div>
    <div class="card panel-card">
        <div class="card-header">{{ __('Assigned trips') }}</div>
        <x-empty-state icon="sign-turn-right" :title="__('No trips assigned')" :text="__('New trips assigned by your operator will appear here.')" />
    </div>
</x-layouts.mobile>
