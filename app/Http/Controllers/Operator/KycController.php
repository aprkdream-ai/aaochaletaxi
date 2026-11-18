<?php

namespace App\Http\Controllers\Operator;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\TenantDocument;
use App\Services\KycService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycController extends Controller
{
    public function index(TenantContext $context): View
    {
        $tenant = $context->get();

        return view('operator.kyc', [
            'tenant' => $tenant,
            'documents' => TenantDocument::query()->with('reviewer:id,name')->latest('id')->get(),
            'required' => KycService::requiredStatus($tenant),
            'types' => DocumentType::cases(),
        ]);
    }

    public function store(Request $request, TenantContext $context, KycService $kyc): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::enum(DocumentType::class)],
            'number' => ['nullable', 'string', 'max:60'],
            'file' => ['required', 'file', 'mimes:'.implode(',', KycService::MIMES), 'max:'.KycService::MAX_KB],
        ], ['file.max' => __('The file must not be larger than 5 MB.')]);

        $count = TenantDocument::query()->count();
        abort_if($count >= 30, 422, __('Document limit reached. Please contact support.'));

        $kyc->upload($context->get(), $request->file('file'), DocumentType::from($data['type']), $data['number'] ?? null, $request->user());

        return back()->with('success', __('Document uploaded. Our team will review it shortly.'));
    }

    public function destroy(TenantDocument $document, KycService $kyc): RedirectResponse
    {
        $kyc->delete($document);

        return back()->with('success', __('Document removed.'));
    }

    public function download(TenantDocument $document, KycService $kyc): StreamedResponse
    {
        return $kyc->stream($document);
    }
}
