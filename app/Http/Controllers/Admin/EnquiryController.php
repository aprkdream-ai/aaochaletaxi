<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'in:enquiry,contact,partner'],
            'state' => ['nullable', 'in:open,handled'],
        ]);
        $filters['state'] ??= 'open';

        $messages = ContactMessage::query()->with('handler:id,name')
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
            ->when($filters['state'] === 'open', fn ($q) => $q->whereNull('handled_at'))
            ->when($filters['state'] === 'handled', fn ($q) => $q->whereNotNull('handled_at'))
            ->when($filters['q'] ?? null, function ($q, $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $like)->orWhere('email', 'like', $like)->orWhere('message', 'like', $like));
            })
            ->latest('id')->paginate(20)->withQueryString();

        return view('admin.enquiries.index', [
            'messages' => $messages,
            'filters' => $filters,
            'openCount' => ContactMessage::query()->whereNull('handled_at')->count(),
        ]);
    }

    public function update(Request $request, ContactMessage $enquiry, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate(['handled' => ['required', 'boolean'], 'admin_notes' => ['nullable', 'string', 'max:500']]);

        $enquiry->forceFill([
            'handled_at' => $data['handled'] ? now() : null,
            'handled_by' => $data['handled'] ? $request->user()->id : null,
            'admin_notes' => $data['admin_notes'] ?? $enquiry->admin_notes,
        ])->save();
        $audit->log('enquiry.'.($data['handled'] ? 'handled' : 'reopened'), $enquiry);

        return back()->with('success', $data['handled'] ? __('Marked as handled.') : __('Moved back to open.'));
    }
}
