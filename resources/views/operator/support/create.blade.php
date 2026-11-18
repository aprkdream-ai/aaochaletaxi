<x-layouts.panel :title="__('New ticket')" panel="operator">
    <x-page-header :title="__('How can we help?')">
        <a href="{{ route('operator.support.index') }}" class="btn btn-light"><x-icon name="arrow-left" />{{ __('Back') }}</a>
    </x-page-header>
    <form method="POST" action="{{ route('operator.support.store') }}" class="card panel-card" style="max-width: 820px" data-loading novalidate>
        @csrf
        <div class="card-body">
            <x-input name="subject" :label="__('Subject')" :value="$subject" required maxlength="150" />
            <div class="row g-2">
                <div class="col-md-6 mb-3"><label class="form-label" for="category">{{ __('Category') }}</label>
                    <select name="category" id="category" class="form-select">@foreach ($categories as $c)<option value="{{ $c->value }}" @selected(old('category', $category->value) === $c->value)>{{ $c->label() }}</option>@endforeach</select></div>
                <div class="col-md-6 mb-3"><label class="form-label" for="priority">{{ __('Priority') }}</label>
                    <select name="priority" id="priority" class="form-select">@foreach (array_slice($priorities, 0, 3) as $p)<option value="{{ $p->value }}" @selected(old('priority', 'normal') === $p->value)>{{ $p->label() }}</option>@endforeach</select></div>
            </div>
            <label class="form-label" for="body">{{ __('Describe the issue') }}<span class="text-danger"> *</span></label>
            <textarea name="body" id="body" rows="7" class="form-control @error('body') is-invalid @enderror" required minlength="10" maxlength="5000">{{ old('body') }}</textarea>
            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="text-end mt-3"><button class="btn btn-primary"><x-icon name="send" />{{ __('Submit ticket') }}</button></div>
        </div>
    </form>
</x-layouts.panel>
