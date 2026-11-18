@php $editing = $branch->exists; @endphp
<x-layouts.panel :title="$editing ? __('Edit branch') : __('Add branch')" panel="operator">
    <x-page-header :title="$editing ? $branch->name : __('Add branch')"><a href="{{ route('operator.branches.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a></x-page-header>
    <form method="POST" action="{{ $editing ? route('operator.branches.update', $branch) : route('operator.branches.store') }}" class="card panel-card" style="max-width: 760px" data-loading novalidate>
        @csrf @if ($editing) @method('PUT') @endif
        <div class="card-body"><div class="row g-2">
            <div class="col-md-8"><x-input name="name" :label="__('Branch name')" :value="$branch->name" required maxlength="120" placeholder="Ahmedabad – Navrangpura" /></div>
            <div class="col-md-4"><x-input name="code" :label="__('Code')" :value="$branch->code" required maxlength="20" placeholder="AMD" style="text-transform:uppercase" /></div>
            <div class="col-md-6"><x-input name="city" :label="__('City')" :value="$branch->city" required /></div>
            <div class="col-md-6"><x-input name="phone" type="tel" :label="__('Phone')" :value="$branch->phone" inputmode="numeric" /></div>
            <div class="col-12"><x-input name="address" :label="__('Address')" :value="$branch->address" maxlength="255" /></div>
            <div class="col-md-6"><x-switch name="is_head_office" :label="__('Head office')" :checked="$branch->is_head_office" /></div>
            <div class="col-md-6"><x-switch name="is_active" :label="__('Active')" :checked="$branch->is_active" /></div>
        </div></div>
        <div class="card-body border-top text-end"><button class="btn btn-primary">{{ $editing ? __('Save changes') : __('Add branch') }}</button></div>
    </form>
</x-layouts.panel>
