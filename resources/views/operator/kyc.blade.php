<x-layouts.panel :title="__('KYC documents')" panel="operator">
    <x-page-header :title="__('KYC verification')" :subtitle="__('Upload documents so we can verify your business. Files are stored privately and only seen by our verification team.')" />

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card panel-card mb-3">
                <div class="card-header">{{ __('Required documents') }} <span class="badge-soft {{ ['verified' => 'ok', 'submitted' => 'warn', 'rejected' => 'danger'][$tenant->kyc_status->value] ?? 'muted' }}">{{ $tenant->kyc_status->label() }}</span></div>
                <div class="list-group list-group-flush list-modern">
                    @foreach ($required as $type => $state)
                        @php $meta = ['approved' => ['check-circle-fill', 'text-success', __('Approved')], 'pending' => ['hourglass-split', 'text-warning', __('Under review')], 'rejected' => ['x-circle-fill', 'text-danger', __('Rejected — upload again')], 'missing' => ['circle', 'text-muted-2', __('Not uploaded')]][$state]; @endphp
                        <div class="list-group-item d-flex align-items-center gap-3"><x-icon :name="$meta[0]" class="{{ $meta[1] }}" /><div><div class="fw-semibold">{{ \App\Enums\DocumentType::from($type)->label() }}</div><div class="small text-muted-2">{{ $meta[2] }}</div></div></div>
                    @endforeach
                </div>
            </div>
            <form method="POST" action="{{ route('operator.kyc.store') }}" enctype="multipart/form-data" class="card panel-card" data-loading novalidate>
                @csrf
                <div class="card-header">{{ __('Upload a document') }}</div>
                <div class="card-body">
                    <div class="mb-3"><label class="form-label" for="type">{{ __('Document type') }}</label>
                        <select name="type" id="type" class="form-select @error('type') is-invalid @enderror">@foreach ($types as $t)<option value="{{ $t->value }}" @selected(old('type') === $t->value)>{{ $t->label() }}</option>@endforeach</select>
                        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                    <x-input name="number" :label="__('Document number (optional)')" maxlength="60" />
                    <div class="mb-3"><label class="form-label" for="file">{{ __('File') }}</label>
                        <input type="file" name="file" id="file" class="form-control @error('file') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">{{ __('PDF, JPG, PNG or WEBP · max 5 MB') }}</div></div>
                    <button class="btn btn-primary w-100"><x-icon name="cloud-upload" />{{ __('Upload') }}</button>
                </div>
            </form>
        </div>
        <div class="col-lg-7">
            <div class="card panel-card">
                <div class="card-header">{{ __('Uploaded documents') }}</div>
                <div class="list-group list-group-flush list-modern">
                    @forelse ($documents as $doc)
                        <div class="list-group-item d-flex flex-wrap align-items-center gap-3">
                            <span class="stat-ico tone-secondary"><x-icon :name="$doc->isImage() ? 'file-earmark-image' : 'file-earmark-pdf'" /></span>
                            <div class="me-auto min-w-0">
                                <div class="fw-semibold">{{ $doc->type->label() }}</div>
                                <div class="small text-muted-2 text-truncate">{{ $doc->original_name }} · {{ $doc->humanSize() }} · {{ $doc->created_at->format('d M Y') }}</div>
                                @if ($doc->review_notes)<div class="small {{ $doc->status->value === 'rejected' ? 'text-danger' : 'text-muted-2' }}"><x-icon name="chat-left-text" /> {{ $doc->review_notes }}</div>@endif
                            </div>
                            <x-status-badge :status="$doc->status" />
                            <a href="{{ route('operator.kyc.download', $doc) }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border" aria-label="{{ __('View') }}"><x-icon name="eye" /></a>
                            @if ($doc->status->value !== 'approved')
                                <form method="POST" action="{{ route('operator.kyc.destroy', $doc) }}" data-confirm="{{ __('Remove this document?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light border text-danger" aria-label="{{ __('Remove') }}"><x-icon name="trash" /></button></form>
                            @endif
                        </div>
                    @empty
                        <x-empty-state icon="folder2-open" :title="__('No documents yet')" :text="__('Start with your PAN card, ID proof and address proof.')" />
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.panel>
