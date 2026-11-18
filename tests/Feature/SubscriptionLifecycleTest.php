<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SubscriptionNotice;
use App\Services\TenantOnboardingService;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SubscriptionLifecycleTest extends TestCase
{
    private Tenant $tenant;

    private Subscription $trial;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $owner = app(TenantOnboardingService::class)->register(['business_name' => 'Ganga Travels', 'city' => 'Varanasi', 'name' => 'G', 'email' => 'g@example.com', 'phone' => '9876509876', 'password' => 'secret123']);
        $this->tenant = $owner->tenant;
        $this->tenant->forceFill(['status' => 'active'])->save();
        $this->trial = Subscription::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
    }

    private function process(): void
    {
        $this->artisan('subscriptions:process')->assertSuccessful();
    }

    public function test_reminders_sent_once_per_stage(): void
    {
        $owner = $this->tenant->ownerUser();

        $this->travelTo($this->trial->ends_at->copy()->subDays(6)->startOfDay()->addHours(10));
        $this->process();
        $this->process(); // idempotent
        Notification::assertSentToTimes($owner, SubscriptionNotice::class, 1);
        $this->assertSame(7, $this->trial->fresh()->reminder_stage);

        $this->travelTo($this->trial->ends_at->copy()->subDays(2)->startOfDay()->addHours(10));
        $this->process();
        $this->travelTo($this->trial->ends_at->copy()->subHours(5));
        $this->process();
        Notification::assertSentToTimes($owner, SubscriptionNotice::class, 3);
        $this->assertSame(1, $this->trial->fresh()->reminder_stage);
    }

    public function test_expiry_grace_then_expired_and_access_rules(): void
    {
        $owner = $this->tenant->ownerUser();
        $this->travelTo($this->trial->ends_at->copy()->addHour());
        $this->process();

        $this->trial->refresh();
        $this->assertSame('grace', $this->trial->status->value);
        $this->assertEquals($this->trial->ends_at->copy()->addDays(7), $this->trial->grace_ends_at);
        Notification::assertSentTo($owner, SubscriptionNotice::class, fn ($n) => $n->kind === 'grace');
        $this->actingAs($owner)->get(route('operator.dashboard'))->assertOk(); // grace keeps access

        $this->travelTo($this->trial->grace_ends_at->copy()->addMinute());
        $this->process();
        $this->assertSame('expired', $this->trial->fresh()->status->value);
        Notification::assertSentTo($owner, SubscriptionNotice::class, fn ($n) => $n->kind === 'expired');
        $this->actingAs($owner)->get(route('operator.dashboard'))->assertRedirect(route('operator.subscription'));
        $this->actingAs($owner)->get(route('operator.support.index'))->assertOk();
    }

    public function test_zero_grace_expires_immediately(): void
    {
        PlatformSetting::setValue('grace_days', '0');
        $this->travelTo($this->trial->ends_at->copy()->addHour());
        $this->process();
        $this->assertSame('expired', $this->trial->fresh()->status->value);
    }

    public function test_extend_from_grace_restores_status(): void
    {
        $this->travelTo($this->trial->ends_at->copy()->addHour());
        $this->process();
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->post(route('admin.subscriptions.extend', $this->trial), ['days' => 5])->assertSessionHas('success');
        $s = $this->trial->fresh();
        $this->assertSame('trialing', $s->status->value);
        $this->assertNull($s->grace_ends_at);
        $this->assertTrue($s->ends_at->isFuture());
    }
}
