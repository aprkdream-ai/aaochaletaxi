<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\Tenancy\TenantContext;
use LogicException;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    private Tenant $a;

    private Tenant $b;

    private TenantContext $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Tenant::factory()->create();
        $this->b = Tenant::factory()->create();
        $this->ctx = app(TenantContext::class);

        $this->ctx->runAs($this->a, fn () => TenantSetting::create(['key' => 'color', 'value' => 'red']));
        $this->ctx->runAs($this->b, fn () => TenantSetting::create(['key' => 'color', 'value' => 'blue']));
    }

    public function test_queries_only_return_current_tenant_rows(): void
    {
        $this->ctx->runAs($this->a, function () {
            $this->assertSame(['red'], TenantSetting::pluck('value')->all());
            $this->assertSame(1, TenantSetting::count());
        });
        $this->ctx->runAs($this->b, fn () => $this->assertSame(['blue'], TenantSetting::pluck('value')->all()));
    }

    public function test_find_by_id_of_other_tenant_returns_null(): void
    {
        $bRow = $this->ctx->runAs($this->b, fn () => TenantSetting::first());
        $this->ctx->runAs($this->a, fn () => $this->assertNull(TenantSetting::find($bRow->id)));
    }

    public function test_no_context_fails_closed(): void
    {
        $this->assertSame(0, TenantSetting::count());
    }

    public function test_platform_bypass_sees_all(): void
    {
        $this->assertSame(2, $this->ctx->runWithoutScope(fn () => TenantSetting::count()));
        $this->assertSame(0, TenantSetting::count(), 'bypass must be restored after callback');
    }

    public function test_create_forces_context_tenant_ignoring_supplied_value(): void
    {
        $row = $this->ctx->runAs($this->a, function () {
            $s = new TenantSetting(['key' => 'x', 'value' => '1']);
            $s->tenant_id = $this->b->id; // malicious attempt
            $s->save();

            return $s;
        });

        $this->assertSame($this->a->id, $row->tenant_id);
    }

    public function test_create_without_context_throws(): void
    {
        $this->expectException(LogicException::class);
        TenantSetting::create(['key' => 'orphan', 'value' => '1']);
    }

    public function test_tenant_id_cannot_be_changed(): void
    {
        $this->expectException(LogicException::class);
        $this->ctx->runAs($this->a, function () {
            $row = TenantSetting::first();
            $row->tenant_id = $this->b->id;
            $row->save();
        });
    }

    public function test_mass_update_and_delete_are_scoped(): void
    {
        $this->ctx->runAs($this->a, fn () => TenantSetting::query()->update(['value' => 'hacked']));
        $this->ctx->runAs($this->a, fn () => TenantSetting::query()->delete());

        $this->ctx->runAs($this->b, fn () => $this->assertSame(['blue'], TenantSetting::pluck('value')->all()));
    }

    public function test_operator_request_context_is_their_tenant(): void
    {
        $plan = SubscriptionPlan::first();
        foreach ([$this->a, $this->b] as $t) {
            $this->ctx->runAs($t, fn () => Subscription::create(['plan_id' => $plan->id, 'billing_cycle' => 'trial', 'status' => 'trialing', 'starts_at' => now(), 'ends_at' => now()->addDays(5)]));
        }
        $this->ctx->reset();

        $ownerA = $this->owner($this->a);
        $this->actingAs($ownerA)->get(route('operator.dashboard'))->assertOk()->assertSee($this->a->name)->assertDontSee($this->b->name);
    }

    public function test_operator_cannot_access_admin_tenant_pages(): void
    {
        $this->actingAs($this->owner($this->a))->get(route('admin.tenants.show', $this->b))->assertForbidden();
    }
}
