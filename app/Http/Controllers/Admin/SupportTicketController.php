<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TicketStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(private SupportTicketService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'mine' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $tickets = SupportTicket::query()->with(['tenant:id,uuid,name', 'assignee:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', [TicketStatus::Open->value, TicketStatus::AwaitingCustomer->value]))
            ->when($filters['mine'] ?? false, fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', '%'.addcslashes($t, '%_\\').'%')->orWhere('subject', 'like', '%'.addcslashes($t, '%_\\').'%')))
            ->orderByRaw("case when status = 'open' then 0 else 1 end")->latest('last_reply_at')
            ->paginate(25)->withQueryString();

        return view('admin.support.index', ['tickets' => $tickets, 'filters' => $filters]);
    }

    public function show(SupportTicket $ticket): View
    {
        return view('admin.support.show', [
            'ticket' => $ticket->load(['tenant:id,uuid,name,phone,email', 'replies.author:id,name,type', 'creator:id,name,email,phone', 'assignee:id,name']),
            'staff' => User::query()->whereIn('type', [UserType::SuperAdmin, UserType::PlatformStaff])->where('status', 'active')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000'], 'internal' => ['nullable', 'boolean']]);
        $this->service->reply($ticket, $request->user(), $data['body'], (bool) ($data['internal'] ?? false));

        return back()->with('success', ($data['internal'] ?? false) ? __('Internal note added.') : __('Reply sent.'));
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        if (isset($data['status'])) {
            $this->service->setStatus($ticket, TicketStatus::from($data['status']), $request->user());
        }
        if ($request->has('assigned_to')) {
            $this->service->assign($ticket, isset($data['assigned_to']) ? User::find($data['assigned_to']) : null);
        }

        return back()->with('success', __('Ticket updated.'));
    }
}
