<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\Invoice;
use App\Models\PlatformSettlement;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Services\Booking\BookingService;
use App\Services\Booking\FareRuleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private Vehicle $vehicle;

    private Driver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['slug' => 'shiv-travels', 'state' => 'Gujarat', 'gstin' => '24ABCDE1234F1Z5', 'legal_name' => 'Shiv Travels Pvt Ltd']);
        $this->owner = $this->owner($this->tenant);
        app(FareRuleService::class)->seedDefaults($this->tenant);
        [$this->vehicle, $this->driver] = $this->as(fn () => [
            Vehicle::create(['registration_number' => 'GJ01AB1234', 'vehicle_category_id' => VehicleCategory::where('slug', 'sedan')->value('id'), 'make' => 'Maruti', 'model' => 'Dzire', 'fuel_type' => 'cng', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active', 'is_available' => true]),
            Driver::create(['name' => 'Suresh', 'phone' => '9811122233', 'licence_number' => 'GJ0120190012345', 'licence_expiry' => now()->addYear(), 'pay_type' => 'per_trip', 'pay_amount' => 500, 'status' => 'active', 'is_available' => true]),
        ]);
    }

    private function as(\Closure $fn)
    {
        return app(TenantContext::class)->runAs($this->tenant, $fn);
    }

    /** One-way 262 km sedan, tolls 320 → completed with actual 262 km. */
    private function completedBooking(array $extras = ['tolls' => 320], string $pickup = '+1 hour'): Booking
    {
        return $this->as(function () use ($extras, $pickup) {
            $svc = app(BookingService::class);
            $b = $svc->create($this->tenant, ['type' => 'outstation_oneway', 'vehicle_category_id' => $this->vehicle->vehicle_category_id, 'customer_name' => 'Priya', 'customer_phone' => '9825012345',
                'pickup_address' => 'Ahmedabad', 'drop_address' => 'Udaipur', 'pickup_at' => now()->modify($pickup), 'distance_km' => 262, 'passengers' => 2], [], [], $this->owner, (string) Str::uuid());
            $svc->confirm($b, $this->owner);
            $r = $svc->assign($b->refresh(), $this->vehicle->id, $this->driver->id, $this->owner);
            $svc->start($b->refresh(), $r['otp'], 10000, $this->owner);
            $svc->complete($b->refresh(), 10262, $extras, $this->owner);

            return $b->refresh();
        });
    }

    public function test_gst_invoice_issue_numbering_and_split(): void
    {
        $b = $this->completedBooking();
        // 262 × 13 + 300 allowance = 3706; GST 5% = 185.30 (CGST 92.65 + SGST 92.65); +320 tolls = 4211.30 → 4211
        $this->assertSame('4211.00', $b->final_total);

        $this->actingAs($this->owner)->post(route('operator.invoices.store', $b), ['gstin' => '24ABC123'])->assertSessionHasErrors('gstin');
        $this->actingAs($this->owner)->post(route('operator.invoices.store', $b), ['name' => 'Acme Pvt Ltd', 'gstin' => '24AAACR1234K1Z5'])->assertRedirect();
        $inv = Invoice::withoutGlobalScopes()->sole();
        $this->assertMatchesRegularExpression('#^SHIV/\d{2}-\d{2}/0001$#', $inv->number);
        $this->assertLessThanOrEqual(16, strlen($inv->number));
        $this->assertSame('3706.00', $inv->taxable);
        $this->assertSame('92.65', $inv->cgst);
        $this->assertSame('92.65', $inv->sgst);
        $this->assertSame('0.00', $inv->igst);
        $this->assertSame('320.00', $inv->reimbursements);
        $this->assertSame('4211.00', $inv->total);
        $this->assertSame('Shiv Travels Pvt Ltd', $inv->seller['name']);
        $this->assertSame('24AAACR1234K1Z5', $inv->buyer['gstin']);
        $this->assertSame(2, $inv->items()->where('taxable', true)->count());   // distance + driver allowance
        $this->assertSame(1, $inv->items()->where('taxable', false)->count());  // tolls (reimbursement)

        $this->actingAs($this->owner)->post(route('operator.invoices.store', $b))->assertSessionHasErrors('invoice');
        $this->actingAs($this->owner)->get(route('operator.invoices.show', $inv))->assertOk()->assertSee('Tax invoice')->assertSee('CGST @ 2.5%')->assertSee('Four thousand two hundred eleven rupees only', false);
        $pdf = $this->actingAs($this->owner)->get(route('operator.invoices.pdf', $inv))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_invoices_are_immutable_and_corrected_by_credit_note(): void
    {
        $b = $this->completedBooking();
        $this->actingAs($this->owner)->post(route('operator.invoices.store', $b));
        $inv = Invoice::withoutGlobalScopes()->sole();

        try {
            $this->as(fn () => $inv->update(['total' => 1]));
            $this->fail('Invoice should be immutable');
        } catch (\LogicException) {
        }

        $this->actingAs($this->owner)->post(route('operator.invoices.credit-note', $inv), ['reason' => 'Wrong GSTIN'])->assertRedirect();
        $cn = Invoice::withoutGlobalScopes()->where('type', 'credit_note')->sole();
        $this->assertMatchesRegularExpression('#^SHIC/\d{2}-\d{2}/0001$#', $cn->number);
        $this->assertSame('credited', $inv->fresh()->status);
        $this->actingAs($this->owner)->post(route('operator.invoices.store', $b), ['inter_state' => 1])->assertRedirect();
        $new = Invoice::withoutGlobalScopes()->where('type', 'invoice')->where('status', 'issued')->sole();
        $this->assertStringEndsWith('/0002', $new->number);
        $this->assertSame('185.30', $new->igst);

        // GST report nets the credit note.
        $this->actingAs($this->owner)->get(route('operator.reports.index', ['tab' => 'gst']))->assertOk()->assertSee($cn->number);
    }

    public function test_customer_can_download_invoice_from_tracking_page(): void
    {
        $b = $this->completedBooking();
        $this->get(route('booking.track.invoice', $b->public_token))->assertNotFound();
        $this->actingAs($this->owner)->post(route('operator.invoices.store', $b));
        auth()->logout();
        $this->get(route('booking.track', $b->public_token))->assertSee('Download invoice');
        $this->get(route('booking.track.invoice', $b->public_token))->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_cannot_invoice_incomplete_or_foreign_bookings(): void
    {
        $b = $this->as(fn () => app(BookingService::class)->create($this->tenant, ['type' => 'local', 'vehicle_category_id' => $this->vehicle->vehicle_category_id, 'customer_name' => 'X', 'customer_phone' => '9825012345', 'pickup_address' => 'A', 'drop_address' => 'B', 'pickup_at' => now()->addDay(), 'distance_km' => 10, 'passengers' => 1], [], [], $this->owner, (string) Str::uuid()));
        $this->actingAs($this->owner)->post(route('operator.invoices.store', $b))->assertSessionHasErrors('invoice');
        $done = $this->completedBooking();
        $other = $this->owner(Tenant::factory()->create());
        $this->actingAs($other)->post(route('operator.invoices.store', $done))->assertNotFound();
    }

    public function test_driver_earnings_and_append_only_payouts(): void
    {
        $this->completedBooking();
        $this->completedBooking([], '+2 days');
        $from = now()->startOfMonth()->toDateString();
        $to = now()->addDays(3)->toDateString();

        $res = $this->actingAs($this->owner)->get(route('operator.payouts.show', [$this->driver, 'from' => $from, 'to' => $to]))->assertOk();
        // per trip 2 × 500 + allowances 2 × 300 = 1600
        $res->assertSee('₹1,600');

        $this->actingAs($this->owner)->post(route('operator.payouts.store', $this->driver), ['from' => $from, 'to' => now()->toDateString(), 'amount' => 1000, 'method' => 'upi'])->assertSessionHas('success');
        $s = DriverSettlement::withoutGlobalScopes()->sole();
        $this->assertSame('1000.00', $s->amount);
        $this->expectException(\LogicException::class);
        $this->as(fn () => $s->delete());
    }

    public function test_expenses_reports_and_csv_export(): void
    {
        $this->completedBooking();
        $this->actingAs($this->owner)->post(route('operator.expenses.store'), ['expense_date' => now()->toDateString(), 'category' => 'rent', 'amount' => 1000, 'notes' => '=HYPERLINK("http://evil")'])->assertSessionHas('success');
        $this->actingAs($this->owner)->get(route('operator.expenses.index'))->assertOk()->assertSee('₹1,000.00');

        $r = $this->actingAs($this->owner)->get(route('operator.reports.index'))->assertOk();
        $r->assertSee('₹3,706')->assertSee('Operating profit');

        SubscriptionPlan::query()->update(['advanced_reports' => true]);
        $csv = $this->actingAs($this->owner)->get(route('operator.reports.export', ['tab' => 'bookings']))->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Priya', $csv);

        // Customer-controlled text cannot become a spreadsheet formula.
        $this->as(fn () => Booking::query()->update(['customer_name' => '=cmd|calc']));
        $csv = $this->actingAs($this->owner)->get(route('operator.reports.export', ['tab' => 'bookings']))->streamedContent();
        $this->assertStringContainsString("'=cmd|calc", $csv);
    }

    public function test_advanced_reports_require_plan(): void
    {
        $this->completedBooking();
        $this->actingAs($this->owner)->get(route('operator.reports.index', ['tab' => 'vehicles']))->assertOk()->assertSee('Advanced reports are part of');
        $this->actingAs($this->owner)->get(route('operator.reports.export', ['tab' => 'vehicles']))->assertForbidden();

        SubscriptionPlan::where('code', 'starter')->update(['advanced_reports' => true]);
        $this->actingAs($this->owner)->get(route('operator.reports.index', ['tab' => 'vehicles']))->assertOk()->assertSee('GJ 01 AB 1234')->assertSee('₹3,706');
        $this->actingAs($this->owner)->get(route('operator.reports.index', ['tab' => 'bookings']))->assertOk()->assertSee('Priya');
    }

    public function test_platform_settlement_of_online_advances(): void
    {
        $b = $this->completedBooking();
        $this->as(fn () => app(BookingService::class)->recordPayment($b, 'advance', 1000, 'razorpay', 'pay_1', null));
        $this->as(fn () => app(BookingService::class)->recordPayment($b->refresh(), 'balance', 500, 'cash', null, $this->owner));

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get(route('admin.finance.settlements'))->assertOk()->assertSee('₹1,000.00');
        $this->actingAs($admin)->post(route('admin.finance.settle', $this->tenant), ['method' => 'bank_transfer', 'reference' => 'UTR1', 'paid_on' => now()->toDateString()])->assertSessionHas('success');
        $s = PlatformSettlement::sole();
        $this->assertSame('1000.00', $s->gross);
        $this->assertSame('20.00', $s->fee);    // default 2%
        $this->assertSame('980.00', $s->net);
        $this->actingAs($admin)->post(route('admin.finance.settle', $this->tenant), ['method' => 'upi', 'reference' => 'UTR2', 'paid_on' => now()->toDateString()])->assertSessionHasErrors('settlement');
        $this->actingAs($admin)->get(route('admin.finance.reports'))->assertOk();
        $this->actingAs($this->owner)->get(route('admin.finance.settlements'))->assertForbidden();
    }

    public function test_finance_permissions(): void
    {
        $bm = User::factory()->operatorOf($this->tenant, 'booking-manager')->create();
        foreach (['operator.invoices.index', 'operator.expenses.index', 'operator.payouts.index', 'operator.reports.index'] as $r) {
            $this->actingAs($bm)->get(route($r))->assertForbidden();
        }
        $acc = User::factory()->operatorOf($this->tenant, 'accountant')->create();
        foreach (['operator.invoices.index', 'operator.expenses.index', 'operator.payouts.index', 'operator.reports.index'] as $r) {
            $this->actingAs($acc)->get(route($r))->assertOk();
        }
    }
}
