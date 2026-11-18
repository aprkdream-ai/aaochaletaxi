<?php

namespace App\Services;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TicketReplied;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function __construct(private TenantContext $context, private SequenceService $sequences, private AuditLogger $audit) {}

    public function open(Tenant $tenant, User $user, string $subject, TicketCategory $category, TicketPriority $priority, string $body): SupportTicket
    {
        return DB::transaction(function () use ($tenant, $user, $subject, $category, $priority, $body) {
            $number = sprintf('TKT-%06d', $this->sequences->next('support_ticket'));

            return $this->context->runAs($tenant, function () use ($user, $subject, $category, $priority, $body, $number) {
                $ticket = new SupportTicket(['subject' => $subject, 'category' => $category, 'priority' => $priority]);
                $ticket->number = $number;
                $ticket->status = TicketStatus::Open;
                $ticket->created_by = $user->id;
                $ticket->last_reply_at = now();
                $ticket->save();
                $this->addReply($ticket, $user, $body, false);

                return $ticket;
            });
        });
    }

    public function reply(SupportTicket $ticket, User $user, string $body, bool $internal = false): SupportTicketReply
    {
        $fromStaff = $user->type->isPlatform();

        if ($ticket->status->isFinal() && ! $fromStaff) {
            throw ValidationException::withMessages(['body' => __('This ticket is closed. Please open a new ticket.')]);
        }

        $tenant = Tenant::findOrFail($ticket->tenant_id);

        $reply = DB::transaction(function () use ($ticket, $user, $body, $internal, $fromStaff, $tenant) {
            return $this->context->runAs($tenant, function () use ($ticket, $user, $body, $internal, $fromStaff) {
                $reply = $this->addReply($ticket, $user, $body, $fromStaff && $internal);

                if (! $reply->is_internal) {
                    $ticket->status = $fromStaff ? TicketStatus::AwaitingCustomer : TicketStatus::Open;
                    $ticket->closed_at = null;
                    $ticket->last_reply_at = now();
                    $ticket->save();
                }

                return $reply;
            });
        });

        if ($fromStaff && ! $internal && $ticket->creator) {
            $ticket->creator->notify(new TicketReplied($ticket));
        }

        return $reply;
    }

    public function setStatus(SupportTicket $ticket, TicketStatus $status, User $user): void
    {
        $old = $ticket->status->value;
        $ticket->status = $status;
        $ticket->closed_at = $status->isFinal() ? now() : null;
        $ticket->save();
        $this->audit->log('support.status_changed', $ticket, ['status' => $old], ['status' => $status->value], $ticket->tenant_id);
    }

    public function assign(SupportTicket $ticket, ?User $staff): void
    {
        if ($staff && ! $staff->type->isPlatform()) {
            throw ValidationException::withMessages(['assigned_to' => __('Tickets can only be assigned to platform staff.')]);
        }
        $ticket->assigned_to = $staff?->id;
        $ticket->save();
    }

    private function addReply(SupportTicket $ticket, User $user, string $body, bool $internal): SupportTicketReply
    {
        $reply = new SupportTicketReply(['body' => $body]);
        $reply->support_ticket_id = $ticket->id;
        $reply->user_id = $user->id;
        $reply->from_staff = $user->type->isPlatform();
        $reply->is_internal = $internal;
        $reply->save();

        return $reply;
    }
}
