<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Http\Controllers\Controller;
use App\Models\TenantDocument;
use App\Services\KycService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class KycController extends Controller
{
    public function review(Request $request, TenantDocument $document, KycService $kyc): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([DocumentStatus::Approved->value, DocumentStatus::Rejected->value])],
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);
        $kyc->review($document, DocumentStatus::from($data['status']), $data['review_notes'] ?? null, $request->user());

        return back()->with('success', __('Document :status.', ['status' => $data['status']]));
    }

    public function download(TenantDocument $document, KycService $kyc): StreamedResponse
    {
        return $kyc->stream($document);
    }
}
