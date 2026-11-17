<form method="POST" action="{{ route('contact.store') }}" data-loading novalidate>
    @csrf
    <input type="hidden" name="type" value="{{ $type ?? 'contact' }}">
    <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="row g-2">
        <div class="col-md-6"><x-input name="name" icon="person" :label="__('Name')" required maxlength="150" autocomplete="name" /></div>
        <div class="col-md-6"><x-input name="phone" type="tel" icon="phone" :label="__('Mobile number')" required inputmode="numeric" maxlength="14" placeholder="98XXXXXXXX" autocomplete="tel-national" /></div>
        <div class="col-md-6"><x-input name="email" type="email" icon="envelope" :label="__('Email')" maxlength="150" autocomplete="email" /></div>
        <div class="col-md-6"><x-input name="subject" icon="tag" :label="__('Subject')" maxlength="150" /></div>
        <div class="col-12 mb-3">
            <label class="form-label" for="f_message">{{ __('Message') }}<span class="text-danger" aria-hidden="true"> *</span></label>
            <textarea name="message" id="f_message" rows="5" maxlength="3000" class="form-control @error('message') is-invalid @enderror" required aria-required="true">{{ old('message') }}</textarea>
            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <button class="btn btn-primary btn-lg" type="submit">{{ __('Send message') }}<x-icon name="send" /></button>
</form>
