<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\FleetPart;
use App\Models\FleetPartMovement;
use App\Models\Tenant;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleExpense;
use App\Models\VehicleIncident;
use App\Models\VehicleLoan;
use App\Models\VehicleOwnerPayout;
use App\Notifications\FleetExpiryDigest;
use App\Services\FleetExpiryService;
use App\Services\VehicleLoanService;
use App\Support\Depreciation;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FleetExtrasTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private Vehicle $vehicle;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->owner($this->tenant);
        $this->vehicle = $this->makeVehicle();
    }

    private function makeVehicle(array $o = [], ?Tenant $t = null): Vehicle
    {
        return app(TenantContext::class)->runAs($t ?? $this->tenant, fn () => Vehicle::create($o + [
            'registration_number' => 'GJ01AB1234', 'vehicle_category_id' => VehicleCategory::where('slug', 'sedan')->value('id'), 'make' => 'Maruti', 'model' => 'Dzire',
            'fuel_type' => 'diesel', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active', 'is_available' => true, 'odometer_km' => 30000,
            'insurance_expiry' => now()->addYear(), 'permit_expiry' => now()->addYear(), 'fitness_expiry' => now()->addYear(), 'puc_expiry' => now()->addYear(),
        ]));
    }

    private function send(string $route, mixed $params, array $data)
    {
        return $this->actingAs($this->owner)->post(route($route, $params), $data);
    }

    // ---------- Tyres & parts ----------

    public function test_part_lifecycle_tracks_km_across_fittings(): void
    {
        $this->send('operator.parts.store', [], ['type' => 'tyre', 'brand' => 'MRF', 'model' => '185/65 R15', 'serial_number' => 'T-001', 'cost' => 4200, 'warranty_until' => now()->addYear()->toDateString()])
            ->assertSessionHasNoErrors();
        $tyre = FleetPart::withoutGlobalScopes()->sole();
        $this->assertSame('in_stock', $tyre->status);

        $this->send('operator.parts.fit', $tyre, ['vehicle_id' => $this->vehicle->id, 'position' => 'FL', 'odometer_km' => 30000, 'moved_on' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->vehicle->forceFill(['odometer_km' => 35000])->save();
        $this->assertSame(5000, $tyre->fresh()->load('vehicle')->totalKm());

        // Second tyre cannot take the same position.
        $this->send('operator.parts.store', [], ['type' => 'tyre', 'brand' => 'CEAT', 'serial_number' => 'T-002']);
        $other = FleetPart::withoutGlobalScopes()->where('serial_number', 'T-002')->sole();
        $this->send('operator.parts.fit', $other, ['vehicle_id' => $this->vehicle->id, 'position' => 'FL', 'moved_on' => now()->toDateString()])->assertSessionHasErrors('position');

        $this->send('operator.parts.remove', $tyre, ['to' => 'in_stock', 'odometer_km' => 29000, 'moved_on' => now()->toDateString()])->assertSessionHasErrors('odometer_km');
        $this->send('operator.parts.remove', $tyre, ['to' => 'in_stock', 'odometer_km' => 36000, 'moved_on' => now()->toDateString()])->assertSessionHasNoErrors();
        $tyre->refresh();
        $this->assertSame('in_stock', $tyre->status);
        $this->assertSame(6000, $tyre->km_run);

        // Refit on another vehicle: km keeps adding up; then scrap.
        $car2 = $this->makeVehicle(['registration_number' => 'GJ01AB5555', 'odometer_km' => 10000]);
        $this->send('operator.parts.fit', $tyre, ['vehicle_id' => $car2->id, 'position' => 'RR', 'moved_on' => now()->toDateString()]);
        $car2->forceFill(['odometer_km' => 12500])->save();
        $this->assertSame(8500, $tyre->fresh()->load('vehicle')->totalKm());
        $this->send('operator.parts.remove', $tyre, ['to' => 'scrapped', 'moved_on' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('scrapped', $tyre->fresh()->status);
        $this->assertSame(8500, $tyre->fresh()->km_run);
        $this->assertSame(['fit', 'remove', 'fit', 'remove', 'scrap'], FleetPartMovement::withoutGlobalScopes()->where('fleet_part_id', $tyre->id)->orderBy('id')->pluck('action')->all());

        $this->actingAs($this->owner)->get(route('operator.parts.index', ['status' => 'scrapped']))->assertOk()->assertSee('MRF')->assertSee('8,500');
        $this->actingAs($this->owner)->get(route('operator.vehicles.show', [$this->vehicle, 'tab' => 'parts']))->assertOk()->assertSee('CEAT');
    }

    public function test_parts_quantity_and_tenant_isolation(): void
    {
        $this->send('operator.parts.store', [], ['type' => 'battery', 'brand' => 'Exide', 'quantity' => 3])->assertSessionHasNoErrors();
        $this->assertSame(3, FleetPart::withoutGlobalScopes()->count());
        $this->send('operator.parts.store', [], ['type' => 'tyre', 'brand' => 'X', 'serial_number' => 'S1', 'quantity' => 2])->assertSessionHasErrors('quantity');

        $otherOwner = $this->owner(Tenant::factory()->create());
        $part = FleetPart::withoutGlobalScopes()->first();
        $this->actingAs($otherOwner)->post(route('operator.parts.fit', $part), ['vehicle_id' => $this->vehicle->id, 'moved_on' => now()->toDateString()])->assertNotFound();
        $foreign = $this->makeVehicle(['registration_number' => 'DL01AB0001'], Tenant::find($otherOwner->tenant_id));
        $this->send('operator.parts.fit', $part, ['vehicle_id' => $foreign->id, 'moved_on' => now()->toDateString()])->assertSessionHasErrors('vehicle_id');
    }

    // ---------- Incidents ----------

    public function test_challan_payment_creates_one_expense_and_claim_flow(): void
    {
        Storage::fake('local');
        $driver = app(TenantContext::class)->runAs($this->tenant, fn () => Driver::create(['name' => 'Ramesh', 'phone' => '9811122233', 'licence_number' => 'GJ01X1', 'licence_expiry' => now()->addYear(), 'pay_type' => 'monthly', 'status' => 'active', 'is_available' => true]));
        $this->send('operator.incidents.store', [], [
            'vehicle_id' => $this->vehicle->id, 'driver_id' => $driver->id, 'type' => 'challan', 'occurred_on' => now()->toDateString(),
            'reference' => 'GJ-CH-7788', 'description' => 'Over speeding on SG Highway', 'amount' => 2000, 'photo' => UploadedFile::fake()->image('c.jpg'),
        ])->assertSessionHasNoErrors();
        $inc = VehicleIncident::withoutGlobalScopes()->sole();
        Storage::disk('local')->assertExists($inc->photo_path);

        $this->send('operator.incidents.pay', $inc, ['amount' => 2000, 'paid_on' => now()->toDateString(), 'payment_method' => 'upi'])->assertSessionHasNoErrors();
        $this->send('operator.incidents.pay', $inc, ['amount' => 2000, 'paid_on' => now()->toDateString()])->assertSessionHasErrors('amount');
        $exp = VehicleExpense::withoutGlobalScopes()->sole();
        $this->assertSame('fine', $exp->category);
        $this->assertSame('GJ-CH-7788', $exp->reference);
        $this->assertSame($exp->id, $inc->fresh()->expense_id);

        $this->send('operator.incidents.claim', $inc, ['claim_status' => 'settled'])->assertSessionHasErrors('settled_amount');
        $this->send('operator.incidents.claim', $inc, ['claim_status' => 'settled', 'insurer' => 'ICICI Lombard', 'claim_number' => 'CL1', 'settled_amount' => 1500])->assertSessionHasNoErrors();
        $this->send('operator.incidents.close', $inc, [])->assertSessionHas('success');
        $this->assertSame('closed', $inc->fresh()->status);

        $this->actingAs($this->owner)->get(route('operator.incidents.index'))->assertOk()->assertSee('GJ-CH-7788')->assertSee('Over speeding');
        $this->actingAs($this->owner)->get(route('operator.incidents.photo', $inc))->assertOk();
        $this->actingAs($this->owner(Tenant::factory()->create()))->get(route('operator.incidents.photo', $inc))->assertNotFound();
    }

    // ---------- Loans & EMI ----------

    public function test_emi_formula(): void
    {
        $this->assertSame('21001.86', VehicleLoanService::emi('1000000', '9.5', 60)); // standard bank EMI for ₹10 L @ 9.5% × 5 y
        $this->assertSame('10000.00', VehicleLoanService::emi('120000', '0', 12));
    }

    public function test_loan_emi_payments_reminders_and_auto_close(): void
    {
        $this->send('operator.loans.store', $this->vehicle, ['lender' => 'HDFC Bank', 'principal' => 30000, 'interest_rate' => 0, 'tenure_months' => 3, 'first_emi_date' => now()->addDays(3)->toDateString()])
            ->assertRedirect(route('operator.vehicles.show', [$this->vehicle, 'tab' => 'loan']));
        $loan = VehicleLoan::withoutGlobalScopes()->sole();
        $this->assertSame('10000.00', $loan->emi_amount);

        // Reminder: EMI 1 due in 3 days (dashboard + digest stage).
        $items = collect(app(FleetExpiryService::class)->itemsFor($this->tenant))->where('kind', 'emi');
        $this->assertSame(3, $items->first()['days']);
        $this->artisan('fleet:expiry-reminders')->assertSuccessful();
        Notification::assertSentTo($this->owner, FleetExpiryDigest::class, fn ($n) => collect($n->items)->contains('kind', 'emi'));
        $this->actingAs($this->owner)->get(route('operator.dashboard'))->assertOk()->assertSee('EMI 1');

        foreach ([1, 2] as $n) {
            $this->send('operator.loans.pay', $loan, ['installment' => $n, 'amount' => 10000, 'paid_on' => now()->toDateString(), 'payment_method' => 'bank_transfer'])->assertSessionHasNoErrors();
        }
        $this->send('operator.loans.pay', $loan, ['installment' => 2, 'amount' => 10000, 'paid_on' => now()->toDateString()])->assertSessionHasErrors('installment');
        $this->assertSame('10000.00', app(VehicleLoanService::class)->outstanding($loan));
        $this->assertSame(2, VehicleExpense::withoutGlobalScopes()->where('category', 'emi')->where('loan_id', $loan->id)->count());

        $this->actingAs($this->owner)->get(route('operator.vehicles.show', [$this->vehicle, 'tab' => 'loan']))->assertOk()->assertSee('HDFC Bank')->assertSee('#3');

        $this->send('operator.loans.pay', $loan, ['installment' => 3, 'amount' => 10000, 'paid_on' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->assertSame('closed', $loan->fresh()->status);
        $this->assertSame([], collect(app(FleetExpiryService::class)->itemsFor($this->tenant))->where('kind', 'emi')->all());
        $this->send('operator.loans.pay', $loan, ['installment' => 3, 'amount' => 1, 'paid_on' => now()->toDateString()])->assertSessionHasErrors('installment');
    }

    // ---------- Depreciation ----------

    public function test_depreciation_book_value(): void
    {
        $p = Carbon::parse('2024-01-01');
        $this->assertSame('722500.00', Depreciation::bookValue('1000000', 'wdv', '15', $p, $p->copy()->addDays(730))); // 2 full years
        $this->assertSame('700000.00', Depreciation::bookValue('1000000', 'slm', '15', $p, $p->copy()->addDays(730)));
        $this->assertSame('0.00', Depreciation::bookValue('100000', 'slm', '50', $p, $p->copy()->addDays(1000)));
        $this->assertSame('1000000.00', Depreciation::bookValue('1000000', 'wdv', '15', $p, $p));

        $this->actingAs($this->owner)->put(route('operator.vehicles.update', $this->vehicle), [
            'registration_number' => 'GJ01AB1234', 'vehicle_category_id' => $this->vehicle->vehicle_category_id, 'make' => 'Maruti', 'model' => 'Dzire',
            'fuel_type' => 'diesel', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active',
            'purchase_date' => now()->subDays(365)->toDateString(), 'purchase_cost' => 800000, 'depreciation_method' => 'wdv', 'depreciation_rate' => 15,
        ])->assertSessionHasNoErrors();
        $this->assertSame('680000.00', $this->vehicle->fresh()->bookValue());
        $this->actingAs($this->owner)->get(route('operator.vehicles.show', $this->vehicle))->assertOk()->assertSee('Book value')->assertSee('₹6,80,000');
        $this->actingAs($this->owner)->get(route('operator.fleet-report.index'))->assertOk()->assertSee('₹6,80,000');
    }

    // ---------- Attached-vehicle owner payouts ----------

    public function test_owner_statement_and_append_only_payout(): void
    {
        $base = ['registration_number' => 'MH12AB0001', 'vehicle_category_id' => $this->vehicle->vehicle_category_id, 'make' => 'Toyota', 'model' => 'Innova',
            'fuel_type' => 'diesel', 'seats' => 7, 'status' => 'active'];
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $base + ['ownership' => 'attached'])->assertSessionHasErrors('owner_name');
        $this->actingAs($this->owner)->post(route('operator.vehicles.store'), $base + ['ownership' => 'attached', 'owner_name' => 'Kiran Patel', 'owner_phone' => '98250 11111',
            'owner_pan' => 'abcde1234f', 'owner_rate_type' => 'per_km', 'owner_rate' => 12, 'owner_bank' => 'kiran@upi'])->assertSessionHasNoErrors();
        $v = Vehicle::withoutGlobalScopes()->where('registration_number', 'MH12AB0001')->sole();
        $this->assertSame('ABCDE1234F', $v->owner_pan);
        $this->assertSame('9825011111', $v->owner_phone);

        app(TenantContext::class)->runAs($this->tenant, function () use ($v) {
            $d = Driver::create(['name' => 'D', 'phone' => '9811100001', 'licence_number' => 'L1', 'licence_expiry' => now()->addYear(), 'pay_type' => 'monthly', 'status' => 'active', 'is_available' => true]);
            foreach ([[1000, 1250], [1250, 1500]] as [$s, $e]) {
                $t = new Trip(['vehicle_id' => $v->id, 'driver_id' => $d->id, 'start_at' => now()->subHours(5), 'end_at' => now()->subHours(1), 'status' => 'completed', 'trip_number' => 'FT-'.$s, 'purpose' => 'x']);
                $t->forceFill(['start_km' => $s, 'end_km' => $e, 'started_at' => now()->subHours(5), 'completed_at' => now()->subHour()])->save();
            }
        });
        $this->actingAs($this->owner)->post(route('operator.vehicles.expenses.store', $v), ['expense_date' => now()->toDateString(), 'category' => 'toll', 'amount' => 500]);

        // 500 km × ₹12 = 6,000 earned − 500 expenses = 5,500 due.
        $this->actingAs($this->owner)->get(route('operator.owner-payouts.index'))->assertOk()->assertSee('Kiran Patel')->assertSee('₹5,500');
        $this->actingAs($this->owner)->get(route('operator.owner-payouts.show', $v))->assertOk()->assertSee('₹6,000.00')->assertSee('kiran@upi');
        $this->actingAs($this->owner)->get(route('operator.owner-payouts.show', $this->vehicle))->assertNotFound(); // owned vehicle

        $this->actingAs($this->owner)->post(route('operator.owner-payouts.store', $v), ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString(),
            'amount' => 5500, 'deductions' => 500, 'method' => 'upi', 'reference' => 'UTR1'])->assertSessionHas('success');
        $payout = VehicleOwnerPayout::withoutGlobalScopes()->sole();
        $this->assertSame('6000.00', $payout->earned);
        $this->assertSame('Kiran Patel', $payout->breakdown['owner']);
        $this->actingAs($this->owner)->get(route('operator.owner-payouts.show', $v))->assertOk()->assertSee('UTR1');

        $this->expectException(\LogicException::class);
        $payout->update(['amount' => 1]);
    }
}
