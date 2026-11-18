@props(['action', 'types'])
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="row g-2 align-items-end" data-loading novalidate>
    @csrf
    <div class="col-sm-6 col-xl-3"><label class="form-label small" for="d_type">{{ __('Type') }}</label><select name="type" id="d_type" class="form-select form-select-sm">@foreach ($types as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
    <div class="col-sm-6 col-xl-3"><label class="form-label small" for="d_number">{{ __('Number') }}</label><input type="text" name="number" id="d_number" class="form-control form-control-sm" maxlength="60"></div>
    <div class="col-sm-6 col-xl-2"><label class="form-label small" for="d_exp">{{ __('Valid till') }}</label><input type="date" name="expires_on" id="d_exp" class="form-control form-control-sm"></div>
    <div class="col-sm-6 col-xl-3"><label class="form-label small" for="d_file">{{ __('File') }} <span class="text-muted-2">(PDF/JPG/PNG · 5 MB)</span></label><input type="file" name="file" id="d_file" class="form-control form-control-sm @error('file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.webp" required></div>
    <div class="col-12 col-xl-1"><button class="btn btn-sm btn-primary w-100"><x-icon name="cloud-upload" /><span class="d-xl-none">{{ __('Upload') }}</span></button></div>
    @error('file')<div class="col-12 text-danger small">{{ $message }}</div>@enderror
</form>
