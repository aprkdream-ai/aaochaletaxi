<?php

namespace App\Http\Controllers\Operator;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(private SupportTicketService $service) {}

    public function index(): View
    {
        return view('operator.support.index', [
            'tickets' => SupportTicket::query()->with('creator:id,name')->latest('last_reply_at')->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $category = TicketCategory::tryFrom((string) $request->query('category')) ?? TicketCategory::Technical;

        return view('operator.support.create', [
            'categories' => TicketCategory::cases(),
            'priorities' => TicketPriority::cases(),
            'category' => $category,
            'subject' => Str::limit((string) $request->query('subject'), 150, ''),
        ]);
    }

    public function store(Request $request, TenantContext $context): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'min:5', 'max:150'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::in([TicketPriority::Low->value, TicketPriority::Normal->value, TicketPriority::High->value])],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $open = SupportTicket::query()->whereIn('status', [TicketStatus::Open->value, TicketStatus::AwaitingCustomer->value])->count();
        if ($open >= 20) {
            return back()->withInput()->withErrors(['subject' => __('You have too many open tickets. Please wait for a response.')]);
        }

        $ticket = $this->service->open($context->get(), $request->user(), $data['subject'], TicketCategory::from($data['category']), TicketPriority::from($data['priority']), $data['body']);

        return redirect()->route('operator.support.show', $ticket)->with('success', __('Ticket :no created. We will reply soon.', ['no' => $ticket->number]));
    }

    public function show(SupportTicket $ticket): View
    {
        return view('operator.support.show', ['ticket' => $ticket->load(['publicReplies.author:id,name,type', 'creator:id,name'])]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);
        $this->service->reply($ticket, $request->user(), $data['body']);

        return back()->with('success', __('Reply sent.'));
    }

    public function close(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $this->service->setStatus($ticket, TicketStatus::Closed, $request->user());

        return back()->with('success', __('Ticket closed.'));
    }
}
