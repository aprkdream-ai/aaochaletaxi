<x-layouts.panel :title="__('Departments')" panel="operator">
    <x-page-header :title="__('Departments')" :subtitle="__('Departments or cost centres that request vehicles — used on trips and in the fleet cost report')" />
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card panel-card"><div class="card-header">{{ __('Add department') }}</div><div class="card-body">
                <form method="POST" action="{{ route('operator.departments.store') }}" data-loading>@csrf
                    <x-input name="name" :label="__('Name')" required maxlength="100" :placeholder="__('e.g. Sales, Admin, Plant 2')" />
                    <x-input name="code" :label="__('Cost centre code')" maxlength="20" />
                    <x-input name="head_name" :label="__('Head / approver')" maxlength="150" />
                    <button class="btn btn-primary w-100"><x-icon name="plus-lg" />{{ __('Add') }}</button>
                </form>
            </div></div>
        </div>
        <div class="col-lg-8">
            <div class="card panel-card">
                <div class="list-group list-group-flush list-modern">
                    @forelse ($departments as $d)
                        <div class="list-group-item" x-data="{ edit: false }">
                            <div class="d-flex flex-wrap align-items-center gap-3" x-show="!edit">
                                <div class="me-auto min-w-0"><div class="fw-semibold">{{ $d->name }} @if ($d->code)<code class="small">{{ $d->code }}</code>@endif</div>
                                    <div class="small text-muted-2">{{ $d->head_name ?: '—' }} · {{ trans_choice('{0} no completed trips|{1} 1 completed trip|[2,*] :n completed trips', $d->trips_count, ['n' => $d->trips_count]) }}</div></div>
                                <span class="badge-soft {{ $d->is_active ? 'ok' : 'muted' }}">{{ $d->is_active ? __('Active') : __('Inactive') }}</span>
                                <button type="button" class="btn btn-sm btn-light border" @click="edit = true"><x-icon name="pencil" />{{ __('Edit') }}</button>
                            </div>
                            <form method="POST" action="{{ route('operator.departments.update', $d) }}" class="row g-2 align-items-end" x-show="edit" x-cloak>@csrf @method('PUT')
                                <div class="col-md-4"><label class="form-label small" for="dn{{ $d->id }}">{{ __('Name') }}</label><input name="name" id="dn{{ $d->id }}" value="{{ $d->name }}" class="form-control form-control-sm" required maxlength="100"></div>
                                <div class="col-md-2"><label class="form-label small" for="dc{{ $d->id }}">{{ __('Code') }}</label><input name="code" id="dc{{ $d->id }}" value="{{ $d->code }}" class="form-control form-control-sm" maxlength="20"></div>
                                <div class="col-md-3"><label class="form-label small" for="dh{{ $d->id }}">{{ __('Head') }}</label><input name="head_name" id="dh{{ $d->id }}" value="{{ $d->head_name }}" class="form-control form-control-sm" maxlength="150"></div>
                                <div class="col-md-1"><input type="hidden" name="is_active" value="0"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="da{{ $d->id }}" @checked($d->is_active)><label class="form-check-label small" for="da{{ $d->id }}">{{ __('Active') }}</label></div></div>
                                <div class="col-md-2 d-flex gap-1"><button class="btn btn-sm btn-primary flex-fill">{{ __('Save') }}</button><button type="button" class="btn btn-sm btn-light border" @click="edit = false" aria-label="{{ __('Cancel') }}"><x-icon name="x-lg" /></button></div>
                            </form>
                        </div>
                    @empty
                        <x-empty-state icon="building" :title="__('No departments yet')" :text="__('Optional — add them to see vehicle cost per department.')" />
                    @endforelse
                </div>
                @error('name')<div class="card-body border-top py-2 small text-danger">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</x-layouts.panel>
