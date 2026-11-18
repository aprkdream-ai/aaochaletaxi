<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TicketReplied;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->owner = $this->owner(Tenant::factory()->create());
        $this->admin = User::factory()->superAdmin()->create();
    }

    private function openTicket(): SupportTicket
    {
        $this->actingAs($this->owner)->post(route('operator.support.store'), [
            'subject' => 'Cannot add vehicle', 'category' => 'technical', 'priority' => 'high', 'body' => 'The add vehicle button gives an error.',
        ])->assertRedirect();

        return SupportTicket::withoutGlobalScopes()->latest('id')->first();
    }

    public function test_full_conversation_flow(): void
    {
        $ticket = $this->openTicket();
        $this->assertSame('TKT-000001', $ticket->number);
        $this->assertSame('open', $ticket->status->value);

        $this->actingAs($this->admin)->get(route('admin.support.index'))->assertOk()->assertSee('Cannot add vehicle');
        $this->actingAs($this->admin)->post(route('admin.support.reply', $ticket), ['body' => 'Internal: check logs', 'internal' => 1]);
        $this->actingAs($this->admin)->post(route('admin.support.reply', $ticket), ['body' => 'Fixed, please retry.']);

        $this->assertSame('awaiting_customer', $ticket->fresh()->status->value);
        Notification::assertSentToTimes($this->owner, TicketReplied::class, 1);

        $this->actingAs($this->owner)->get(route('operator.support.show', $ticket))
            ->assertOk()->assertSee('Fixed, please retry.')->assertDontSee('Internal: check logs')->assertSee('AaoChaleTaxi Support');

        $this->actingAs($this->owner)->post(route('operator.support.reply', $ticket), ['body' => 'Works now, thanks']);
        $this->assertSame('open', $ticket->fresh()->status->value);

        $this->actingAs($this->owner)->post(route('operator.support.close', $ticket))->assertSessionHas('success');
        $this->actingAs($this->owner)->post(route('operator.support.reply', $ticket), ['body' => 'one more'])->assertSessionHasErrors('body');
    }

    public function test_tickets_are_tenant_isolated(): void
    {
        $ticket = $this->openTicket();
        $other = $this->owner(Tenant::factory()->create());
        $this->actingAs($other)->get(route('operator.support.show', $ticket))->assertNotFound();
        $this->actingAs($other)->post(route('operator.support.reply', $ticket), ['body' => 'hi'])->assertNotFound();
        $this->actingAs($other)->get(route('operator.support.index'))->assertDontSee('Cannot add vehicle');
    }

    public function test_assign_and_status_and_permissions(): void
    {
        $ticket = $this->openTicket();
        $this->actingAs($this->admin)->patch(route('admin.support.update', $ticket), ['status' => 'resolved', 'assigned_to' => $this->admin->id])->assertSessionHas('success');
        $ticket->refresh();
        $this->assertSame('resolved', $ticket->status->value);
        $this->assertSame($this->admin->id, $ticket->assigned_to);

        $this->actingAs($this->admin)->patch(route('admin.support.update', $ticket), ['assigned_to' => $this->owner->id])->assertSessionHasErrors('assigned_to');

        $finance = User::factory()->platformStaff()->create();
        $finance->roles()->attach(Role::forTenant(null)->where('slug', 'platform-finance')->value('id'));
        $this->actingAs($finance)->get(route('admin.support.show', $ticket))->assertForbidden();
    }

    public function test_validation(): void
    {
        $this->actingAs($this->owner)->post(route('operator.support.store'), ['subject' => 'Hi', 'category' => 'x', 'priority' => 'urgent', 'body' => 'short'])
            ->assertSessionHasErrors(['subject', 'category', 'priority', 'body']);
    }
}
