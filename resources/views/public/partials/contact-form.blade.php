<form method="POST" action="{{ route('contact.store') }}" data-loading novalidate>
    @csrf
    <input type="hidden" name="type" value="{{ $type ?? 'contact' }}">
    <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off" aria-hidden="true">
    <div class="row">
        <div class="col-md-6"><x-input name="name" :label="__('Name')" required maxlength="150" /></div>
        <div class="col-md-6"><x-input name="phone" type="tel" :label="__('Mobile number')" required inputmode="numeric" placeholder="98XXXXXXXX" /></div>
        <div class="col-md-6"><x-input name="email" type="email" :label="__('Email')" maxlength="150" /></div>
        <div class="col-md-6"><x-input name="subject" :label="__('Subject')" maxlength="150" /></div>
        <div class="col-12 mb-3">
            <label class="form-label" for="f_message">{{ __('Message') }} <span class="text-danger">*</span></label>
            <textarea name="message" id="f_message" rows="4" maxlength="3000" class="form-control @error('message') is-invalid @enderror" required>{{ old('message') }}</textarea>
            @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <button class="btn btn-primary px-4" type="submit">{{ __('Send message') }}</button>
</form>
