<div class="form-check my-3">
    <input class="form-check-input @error('terms') is-invalid @enderror" type="checkbox" name="terms" value="1" id="terms" @checked(old('terms'))>
    <label class="form-check-label small" for="terms">
        {{ __('I agree to the') }} <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">{{ __('Terms & conditions') }}</a>
        {{ __('and') }} <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">{{ __('Privacy policy') }}</a>.
    </label>
    @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>
