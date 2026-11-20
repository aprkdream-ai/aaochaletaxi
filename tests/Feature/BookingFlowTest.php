<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\FareRule;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\Booking\FareRuleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private int $sedan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->owner($this->tenant);
        app(FareRuleService::class)->seedDefaults($this->tenant);
        $this->sedan = VehicleCategory::where('slug', 'sedan')->value('id');
    }

    private function payload(array $o = []): array
    {
        return array_merge([
            'type' => 'outstation_oneway', 'vehicle_category_id' => $this->sedan, 'customer_name' => 'Priya Shah', 'customer_phone' => '98250 12345',
            'pickup_address' => 'Ahmedabad', 'drop_address' => 'Udaipur', 'pickup_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i'),
            'distance_km' => 260, 'passengers' => 3, 'tolls' => 300, 'idempotency_key' => (string) Str::uuid(),
        ], $o);
    }

    private function fleet(Tenant $t, string $reg = 'GJ01AB1234', string $phone = '9811122233'): array
    {
        return app(TenantContext::class)->runAs($t, fn () => [
            Vehicle::create(['registration_number' => $reg, 'vehicle_category_id' => $this->sedan, 'make' => 'Maruti', 'model' => 'Dzire', 'fuel_type' => 'cng', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active', 'is_available' => true, 'insurance_expiry' => now()->addYear()]),
            Driver::create(['name' => 'Suresh', 'phone' => $phone, 'licence_number' => 'GJ0120190012'.substr($phone, -3), 'licence_expiry' => now()->addYears(2), 'pay_type' => 'monthly', 'status' => 'active', 'is_available' => true]),
        ]);
    }

    private function create(array $o = []): Booking
    {
        $this->actingAs($this->owner)->post(route('operator.bookings.store'), $this->payload($o))->assertSessionHasNoErrors();

        return Booking::withoutGlobalScopes()->latest('id')->first();
    }

    public function test_full_lifecycle_with_otp_final_fare_and_payments(): void
    {
        $b = $this->create();
        $this->assertSame('quoted', $b->status->value);
        $this->assertMatchesRegularExpression('/^BK\d{4}-0001$/', $b->booking_number);
        $this->assertSame('9811122233' !== '' ? '9825012345' : '', $b->customer_phone);
        // Sedan one-way default: 260 km × ₹13 + ₹300 allowance = 3680 → +5% = 3864 + 300 tolls
        $this->assertSame('4164.00', $b->quoted_total);
        $this->assertSame(1, $b->quotations()->count());

        $this->actingAs($this->owner)->post(route('operator.bookings.confirm', $b))->assertSessionHas('success');
        $this->actingAs($this->owner)->post(route('operator.bookings.payments.store', $b), ['kind' => 'advance', 'amount' => 1000, 'method' => 'upi'])->assertSessionHas('success');
        $this->assertSame('advance_paid', $b->fresh()->payment_status->value);

        [$v, $d] = $this->fleet($this->tenant);
        $res = $this->actingAs($this->owner)->post(route('operator.bookings.assign', $b), ['vehicle_id' => $v->id, 'driver_id' => $d->id]);
        $res->assertSessionHas('success');
        preg_match('/OTP: (\d{4})/', session('success'), $m);
        $otp = $m[1];
        $this->assertSame('assigned', $b->fresh()->status->value);

        $this->actingAs($this->owner)->post(route('operator.bookings.start', $b), ['otp' => $otp === '0000' ? '1111' : '0000', 'start_km' => 42000])->assertSessionHasErrors('otp');
        $this->actingAs($this->owner)->post(route('operator.bookings.start', $b), ['otp' => $otp, 'start_km' => 42000])->assertSessionHas('success');
        $this->assertSame('started', $b->fresh()->status->value);

        $this->actingAs($this->owner)->post(route('operator.bookings.complete', $b), ['end_km' => 41000])->assertSessionHasErrors('end_km');
        $this->actingAs($this->owner)->post(route('operator.bookings.complete', $b), ['end_km' => 42300, 'tolls' => 450, 'parking' => 50])->assertSessionHas('success');
        $b->refresh();
        $this->assertSame('completed', $b->status->value);
        // 300 km actual × 13 + 300 = 4200 + 210 GST + 500 = 4910
        $this->assertSame('4910.00', $b->final_total);
        $this->assertSame(42300, $v->fresh()->odometer_km);

        $this->actingAs($this->owner)->post(route('operator.bookings.payments.store', $b), ['kind' => 'balance', 'amount' => 3910, 'method' => 'cash'])->assertSessionHas('success');
        $this->assertSame('paid', $b->fresh()->payment_status->value);
        $this->assertSame('0.00', $b->fresh()->balance());

        $this->actingAs($this->owner)->get(route('operator.bookings.show', $b))->assertOk()->assertSee('₹4,910.00')->assertSee('Fully paid');
        $this->assertSame(6, $b->statusLogs()->count());
    }

    public function test_rates_are_frozen_at_quote_time(): void
    {
        $b = $this->create();
        FareRule::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->update(['per_km' => 99]);
        $this->actingAs($this->owner)->post(route('operator.bookings.confirm', $b));
        [$v, $d] = $this->fleet($this->tenant);
        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $b), ['vehicle_id' => $v->id, 'driver_id' => $d->id]);
        preg_match('/OTP: (\d{4})/', session('success'), $m);
        $this->actingAs($this->owner)->post(route('operator.bookings.start', $b), ['otp' => $m[1]]);
        $this->actingAs($this->owner)->post(route('operator.bookings.complete', $b), ['tolls' => 300]);
        $this->assertSame('4164.00', $b->fresh()->final_total, 'final fare uses snapshot rates, not the edited rule');
    }

    public function test_double_booking_is_prevented_for_vehicle_and_driver(): void
    {
        [$v, $d] = $this->fleet($this->tenant);
        [, $d2] = $this->fleet($this->tenant, 'GJ01AB9999', '9811100000');
        $a = $this->create(['pickup_at' => now()->addDays(2)->setTime(9, 0)->format('Y-m-d H:i')]);
        $b = $this->create(['pickup_at' => now()->addDays(2)->setTime(11, 0)->format('Y-m-d H:i'), 'idempotency_key' => (string) Str::uuid()]);
        $c = $this->create(['pickup_at' => now()->addDays(5)->setTime(9, 0)->format('Y-m-d H:i'), 'idempotency_key' => (string) Str::uuid()]);
        foreach ([$a, $b, $c] as $x) {
            $this->actingAs($this->owner)->post(route('operator.bookings.confirm', $x));
        }

        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $a), ['vehicle_id' => $v->id, 'driver_id' => $d->id])->assertSessionHas('success');
        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $b), ['vehicle_id' => $v->id, 'driver_id' => $d2->id])->assertSessionHasErrors('vehicle_id');
        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $b), ['vehicle_id' => Vehicle::withoutGlobalScopes()->where('registration_number', 'GJ01AB9999')->value('id'), 'driver_id' => $d->id])->assertSessionHasErrors('driver_id');
        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $c), ['vehicle_id' => $v->id, 'driver_id' => $d->id])->assertSessionHas('success'); // different day

        // Cancelling A frees the vehicle for B.
        $this->actingAs($this->owner)->post(route('operator.bookings.cancel', $a), ['reason' => 'Customer cancelled'])->assertSessionHas('success');
        $this->assertSame('released', Trip::withoutGlobalScopes()->where('booking_id', $a->id)->value('status'));
        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $b), ['vehicle_id' => $v->id, 'driver_id' => $d2->id])->assertSessionHas('success');
    }

    public function test_unavailable_vehicle_or_expired_documents_block_assignment(): void
    {
        [$v, $d] = $this->fleet($this->tenant);
        $b = $this->create();
        $this->actingAs($this->owner)->post(route('operator.bookings.confirm', $b));
        $v->forceFill(['insurance_expiry' => now()->subDay()])->save();
        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $b), ['vehicle_id' => $v->id, 'driver_id' => $d->id])->assertSessionHasErrors('vehicle_id');
        $v->forceFill(['insurance_expiry' => now()->addYear()])->save();
        $d->forceFill(['is_available' => false])->save();
        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $b), ['vehicle_id' => $v->id, 'driver_id' => $d->id])->assertSessionHasErrors('driver_id');
    }

    public function test_idempotent_create_and_invalid_transitions(): void
    {
        $key = (string) Str::uuid();
        $this->create(['idempotency_key' => $key]);
        $this->create(['idempotency_key' => $key]);
        $this->assertSame(1, Booking::withoutGlobalScopes()->count());

        $b = Booking::withoutGlobalScopes()->sole();
        $this->actingAs($this->owner)->post(route('operator.bookings.start', $b), ['otp' => '1234'])->assertSessionHasErrors('status');
        $this->actingAs($this->owner)->post(route('operator.bookings.complete', $b), [])->assertSessionHasErrors('status');
    }

    public function test_enquiry_without_distance_then_quote(): void
    {
        $b = $this->create(['distance_km' => null]);
        $this->assertSame('enquiry', $b->status->value);
        $this->assertNull($b->quoted_total);
        $this->actingAs($this->owner)->post(route('operator.bookings.confirm', $b))->assertSessionHasErrors('status');
        $this->actingAs($this->owner)->post(route('operator.bookings.quote', $b), ['distance_km' => 120])->assertSessionHas('success');
        $this->assertSame('quoted', $b->fresh()->status->value);
    }

    public function test_preview_endpoint_and_validation(): void
    {
        $this->actingAs($this->owner)->postJson(route('operator.bookings.preview'), ['type' => 'local', 'vehicle_category_id' => $this->sedan, 'pickup_at' => now()->addDay()->format('Y-m-d H:i'), 'distance_km' => 12])
            ->assertOk()->assertJsonStructure(['lines', 'gst', 'total', 'billable_km']);
        $this->actingAs($this->owner)->post(route('operator.bookings.store'), $this->payload(['type' => 'outstation_round', 'return_at' => null]))->assertSessionHasErrors('return_at');
        $this->actingAs($this->owner)->post(route('operator.bookings.store'), $this->payload(['pickup_at' => now()->subDays(2)->format('Y-m-d H:i'), 'customer_phone' => '123']))->assertSessionHasErrors(['pickup_at', 'customer_phone']);
    }

    public function test_refund_and_append_only_payments(): void
    {
        $b = $this->create();
        $this->actingAs($this->owner)->post(route('operator.bookings.confirm', $b));
        $this->actingAs($this->owner)->post(route('operator.bookings.payments.store', $b), ['kind' => 'advance', 'amount' => 1000, 'method' => 'upi']);
        $this->actingAs($this->owner)->post(route('operator.bookings.payments.store', $b), ['kind' => 'refund', 'amount' => 5000, 'method' => 'upi'])->assertSessionHasErrors('amount');
        $this->actingAs($this->owner)->post(route('operator.bookings.cancel', $b), ['reason' => 'Plans changed']);
        $this->actingAs($this->owner)->post(route('operator.bookings.payments.store', $b), ['kind' => 'refund', 'amount' => 1000, 'method' => 'upi'])->assertSessionHas('success');
        $b->refresh();
        $this->assertSame('0.00', $b->amount_paid);
        $this->assertSame('refunded', $b->payment_status->value);

        $p = Payment::withoutGlobalScopes()->first();
        $this->expectException(\LogicException::class);
        app(TenantContext::class)->runAs($this->tenant, fn () => $p->update(['amount' => 1]));
    }

    public function test_tenant_isolation_and_permissions(): void
    {
        $b = $this->create();
        $other = $this->owner(Tenant::factory()->create());
        $this->actingAs($other)->get(route('operator.bookings.show', $b))->assertNotFound();
        $this->actingAs($other)->post(route('operator.bookings.confirm', $b))->assertNotFound();

        [$foreignV, $foreignD] = $this->fleet(Tenant::find($other->tenant_id), 'MH12ZZ0001', '9811199999');
        $this->actingAs($this->owner)->post(route('operator.bookings.confirm', $b));
        $this->actingAs($this->owner)->post(route('operator.bookings.assign', $b), ['vehicle_id' => $foreignV->id, 'driver_id' => $foreignD->id])->assertSessionHasErrors('vehicle_id');

        $accountant = User::factory()->operatorOf($this->tenant, 'accountant')->create();
        $this->actingAs($accountant)->get(route('operator.bookings.show', $b))->assertOk();
        $this->actingAs($accountant)->get(route('operator.bookings.create'))->assertForbidden();
        $this->actingAs($accountant)->post(route('operator.bookings.payments.store', $b), ['kind' => 'advance', 'amount' => 100, 'method' => 'cash'])->assertSessionHas('success');

        $bm = User::factory()->operatorOf($this->tenant, 'booking-manager')->create();
        $this->actingAs($bm)->get(route('operator.fares.index'))->assertForbidden();
    }

    public function test_fare_rules_page_and_update(): void
    {
        $this->actingAs($this->owner)->get(route('operator.fares.index', ['type' => 'hourly']))->assertOk()->assertSee('Sedan');
        $rule = FareRule::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('vehicle_category_id', $this->sedan)->where('booking_type', 'local')->sole();
        $this->actingAs($this->owner)->put(route('operator.fares.update', $rule), ['per_km' => 15, 'base_fare' => 120, 'min_km' => 6, 'night_start' => '23:00', 'night_end' => '05:00', 'gst_percent' => 5, 'is_active' => 1])->assertRedirect();
        $this->assertSame('15.00', $rule->fresh()->per_km);
        $this->actingAs($this->owner)->put(route('operator.fares.update', $rule), ['per_km' => 15, 'night_start' => '23:00', 'night_end' => '05:00', 'gst_percent' => 7])->assertSessionHasErrors('gst_percent');
    }

    public function test_new_operator_gets_default_fare_rules(): void
    {
        $this->post('/logout');
        $this->post(route('register.operator.store'), ['business_name' => 'Fresh Cabs', 'city' => 'Pune', 'name' => 'F', 'email' => 'f@example.com', 'phone' => '9876566666', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => 1]);
        $t = Tenant::where('name', 'Fresh Cabs')->sole();
        $this->assertSame(7 * 8, FareRule::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_operator_sees_customer_link_and_quote_share_includes_it(): void
    {
        $b = $this->create();
        $url = route('booking.track', $b->public_token);
        $this->actingAs($this->owner)->get(route('operator.bookings.show', $b))->assertOk()->assertSee($url, false)->assertSee(rawurlencode($url), false);
    }
}
