<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Driver;
use App\Models\PaymentOrder;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Notifications\OperatorBookingAlert;
use App\Services\Booking\BookingService;
use App\Services\Booking\FareRuleService;
use App\Services\Messaging\OtpService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    private int $sedan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create(['slug' => 'shiv-travels', 'name' => 'Shiv Travels']);
        $this->owner = $this->owner($this->tenant);
        app(FareRuleService::class)->seedDefaults($this->tenant);
        $this->sedan = VehicleCategory::where('slug', 'sedan')->value('id');
    }

    private function mapsOn(float $km = 262): void
    {
        PlatformSetting::setValue('google_maps_api_key', 'AIza-test', true);
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'OK', 'origin_addresses' => ['A'], 'destination_addresses' => ['B'],
            'rows' => [['elements' => [['status' => 'OK', 'distance' => ['value' => (int) ($km * 1000)], 'duration' => ['value' => 14400]]]]]])]);
    }

    private function trip(array $o = []): array
    {
        return array_merge(['type' => 'outstation_oneway', 'vehicle_category_id' => $this->sedan, 'pickup_address' => 'Ahmedabad', 'drop_address' => 'Udaipur',
            'pickup_at' => now()->addDays(2)->setTime(8, 0)->format('Y-m-d H:i'), 'passengers' => 2, 'customer_name' => 'Priya Shah', 'customer_phone' => '9825012345',
            'idempotency_key' => (string) Str::uuid()], $o);
    }

    private function verifyPhone(string $phone = '9825012345'): void
    {
        $this->postJson(route('microsite.otp.send', 'shiv-travels'), ['phone' => $phone])->assertOk();
        $this->postJson(route('microsite.otp.verify', 'shiv-travels'), ['phone' => $phone, 'otp' => OtpService::$lastCode, 'name' => 'Priya Shah'])->assertOk();
    }

    public function test_page_availability(): void
    {
        $this->get(route('microsite.show', 'shiv-travels'))->assertOk()->assertSee('Shiv Travels')->assertSee('Book your ride')->assertSee('"@type":"TaxiService"', false);
        Tenant::factory()->pending()->create(['slug' => 'pending-co']);
        $this->get('/o/pending-co')->assertNotFound();
        $this->get('/o/no-such')->assertNotFound();
        $this->tenant->forceFill(['settings' => ['booking_page_enabled' => false]])->save();
        $this->get('/o/shiv-travels')->assertNotFound();
    }

    public function test_fare_is_computed_server_side_only(): void
    {
        $this->postJson(route('microsite.preview', 'shiv-travels'), $this->trip())->assertStatus(422); // no maps → quote by call
        $this->mapsOn(262);
        $res = $this->postJson(route('microsite.preview', 'shiv-travels'), $this->trip(['distance_km' => 1]))->assertOk();
        $this->assertEquals(262, $res->json('estimated_km'));
        $this->assertSame('3891.00', $res->json('total')); // 262 × ₹13 + ₹300 = 3706 + 5% GST = 3891.30 → ₹3,891
    }

    public function test_otp_required_then_booking_created_and_customer_logged_in(): void
    {
        Notification::fake();
        $this->mapsOn(100);
        $this->post(route('microsite.store', 'shiv-travels'), $this->trip())->assertSessionHasErrors('customer_phone');
        $this->assertSame(0, Booking::withoutGlobalScopes()->count());

        $this->postJson(route('microsite.otp.send', 'shiv-travels'), ['phone' => '9825012345'])->assertOk();
        $this->postJson(route('microsite.otp.verify', 'shiv-travels'), ['phone' => '9825012345', 'otp' => '000000'])->assertStatus(422)->assertJsonValidationErrors('otp');
        $this->postJson(route('microsite.otp.verify', 'shiv-travels'), ['phone' => '9825012345', 'otp' => OtpService::$lastCode, 'name' => 'Priya Shah'])->assertOk();
        $customer = User::where('phone', '9825012345')->sole();
        $this->assertSame('customer', $customer->type->value);
        $this->assertAuthenticatedAs($customer);

        $res = $this->post(route('microsite.store', 'shiv-travels'), $this->trip());
        $b = Booking::withoutGlobalScopes()->sole();
        $res->assertRedirect(route('booking.track', $b->public_token));
        $this->assertSame('website', $b->source);
        $this->assertTrue($b->phone_verified);
        $this->assertSame($customer->id, $b->customer_user_id);
        $this->assertSame('quoted', $b->status->value);
        $this->assertSame($this->tenant->id, $b->tenant_id);
        Notification::assertSentTo($this->owner, OperatorBookingAlert::class, fn ($n) => $n->kind === 'new_online');

        $this->get(route('account.dashboard'))->assertOk()->assertSee($b->booking_number);
    }

    public function test_without_maps_booking_is_an_enquiry(): void
    {
        $this->verifyPhone();
        $this->post(route('microsite.store', 'shiv-travels'), $this->trip(['distance_km' => 5]));
        $b = Booking::withoutGlobalScopes()->sole();
        $this->assertSame('enquiry', $b->status->value);
        $this->assertNull($b->distance_km, 'client distance ignored');
        $this->get(route('booking.track', $b->public_token))->assertOk()->assertSee('will call you shortly');
    }

    public function test_otp_lockout_and_rate_limit(): void
    {
        $this->postJson(route('microsite.otp.send', 'shiv-travels'), ['phone' => '9825012345'])->assertOk();
        foreach (range(1, 5) as $i) {
            $this->postJson(route('microsite.otp.verify', 'shiv-travels'), ['phone' => '9825012345', 'otp' => '000000'])->assertStatus(422);
        }
        $this->postJson(route('microsite.otp.verify', 'shiv-travels'), ['phone' => '9825012345', 'otp' => OtpService::$lastCode])->assertStatus(422)->assertJsonValidationErrors('otp');

        $this->postJson(route('microsite.otp.send', 'shiv-travels'), ['phone' => '9825012345'])->assertOk();
        $this->postJson(route('microsite.otp.send', 'shiv-travels'), ['phone' => '9825012345'])->assertOk();
        $this->postJson(route('microsite.otp.send', 'shiv-travels'), ['phone' => '9825012345'])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_tracking_page_shows_trip_otp_and_customer_can_cancel_and_review(): void
    {
        Notification::fake();
        $this->mapsOn(24);
        $this->verifyPhone();
        $this->post(route('microsite.store', 'shiv-travels'), $this->trip(['type' => 'airport']));
        $b = Booking::withoutGlobalScopes()->sole();
        $svc = app(BookingService::class);
        app(TenantContext::class)->runAs($this->tenant, function () use ($svc, $b) {
            $v = Vehicle::create(['registration_number' => 'GJ01AB1234', 'vehicle_category_id' => $this->sedan, 'make' => 'Maruti', 'model' => 'Dzire', 'fuel_type' => 'cng', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active', 'is_available' => true]);
            $d = Driver::create(['name' => 'Suresh', 'phone' => '9811122233', 'licence_number' => 'GJ0120190012345', 'licence_expiry' => now()->addYear(), 'pay_type' => 'monthly', 'status' => 'active', 'is_available' => true]);
            $svc->confirm($b, $this->owner);
            $res = $svc->assign($b->refresh(), $v->id, $d->id, $this->owner);
            $this->get(route('booking.track', $b->public_token))->assertOk()->assertSee($res['otp'])->assertSee('Suresh')->assertSee('GJ 01 AB 1234');
            $svc->start($b->refresh(), $res['otp'], null, $this->owner);
            $svc->complete($b->refresh(), null, [], $this->owner);
        });

        $this->post(route('booking.track.cancel', $b->public_token), ['reason' => 'x'])->assertSessionHasErrors('reason');
        $this->post(route('booking.track.review', $b->public_token), ['rating' => 5, 'comment' => 'Very punctual driver'])->assertSessionHas('success');
        $this->post(route('booking.track.review', $b->public_token), ['rating' => 1])->assertSessionHasErrors('rating');
        $this->get(route('microsite.show', 'shiv-travels'))->assertSee('Very punctual driver')->assertSee('"ratingValue":5', false);

        // A second booking can be cancelled by the customer.
        $this->post(route('microsite.store', 'shiv-travels'), $this->trip(['idempotency_key' => (string) Str::uuid()]));
        $b2 = Booking::withoutGlobalScopes()->latest('id')->first();
        $this->post(route('booking.track.cancel', $b2->public_token), ['reason' => 'Plans changed'])->assertSessionHas('success');
        $this->assertSame('cancelled', $b2->fresh()->status->value);
        Notification::assertSentTo($this->owner, OperatorBookingAlert::class, fn ($n) => $n->kind === 'cancelled_by_customer');
    }

    public function test_many_fare_previews_do_not_block_otp(): void
    {
        $this->mapsOn(50);
        foreach (range(1, 15) as $i) {
            $this->postJson(route('microsite.preview', 'shiv-travels'), $this->trip())->assertOk();
        }
        $this->postJson(route('microsite.otp.send', 'shiv-travels'), ['phone' => '9825012345'])->assertOk();
    }

    public function test_tokens_are_unguessable_and_scoped(): void
    {
        $this->get('/b/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/b/short')->assertNotFound();
    }

    public function test_online_advance_with_razorpay_callback_and_webhook(): void
    {
        Notification::fake();
        $this->mapsOn(100);
        PlatformSetting::setValue('razorpay_mode', 'test');
        PlatformSetting::setValue('razorpay_key_id', 'rzp_test_X');
        PlatformSetting::setValue('razorpay_key_secret', 'sec', true);
        PlatformSetting::setValue('razorpay_webhook_secret', 'wh', true);
        $this->tenant->forceFill(['settings' => ['online_advance_percent' => 20]])->save();

        $this->verifyPhone();
        $this->post(route('microsite.store', 'shiv-travels'), $this->trip());
        $b = Booking::withoutGlobalScopes()->sole(); // 100 km × 13 + 300 = 1600 + 80 = 1680
        $this->assertSame('1680.00', $b->quoted_total);
        $this->get(route('booking.track', $b->public_token))->assertSee('Pay advance ₹336');

        Http::fake(['api.razorpay.com/v1/orders' => Http::response(['id' => 'order_B1', 'amount' => 33600, 'currency' => 'INR'])]);
        $this->postJson(route('booking.track.pay', $b->public_token))->assertOk()->assertJson(['order_id' => 'order_B1', 'amount' => 33600]);
        $this->assertSame('booking', PaymentOrder::withoutGlobalScopes()->sole()->purpose);

        $sig = hash_hmac('sha256', 'order_B1|pay_B1', 'sec');
        $this->post(route('booking.track.verify', $b->public_token), ['razorpay_order_id' => 'order_B1', 'razorpay_payment_id' => 'pay_B1', 'razorpay_signature' => $sig])->assertSessionHas('success');
        $b->refresh();
        $this->assertSame('confirmed', $b->status->value);
        $this->assertSame('336.00', $b->amount_paid);
        $this->assertSame('advance_paid', $b->payment_status->value);

        $body = json_encode(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => ['id' => 'pay_B1', 'order_id' => 'order_B1', 'amount' => 33600, 'status' => 'captured']]]]);
        $this->call('POST', route('webhooks.razorpay'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_RAZORPAY_SIGNATURE' => hash_hmac('sha256', $body, 'wh')], $body)->assertOk();
        $this->assertSame('336.00', $b->fresh()->amount_paid, 'webhook after callback is idempotent');
        Notification::assertSentTo($this->owner, OperatorBookingAlert::class, fn ($n) => $n->kind === 'paid_online');
    }

    public function test_otp_login_for_customers(): void
    {
        $this->get(route('login'))->assertSee('Mobile OTP');
        $this->post(route('login.otp.send'), ['phone' => '9876501111'])->assertSessionHas('otp_sent');
        $this->post(route('login.otp.verify'), ['otp' => OtpService::$lastCode])->assertRedirect(route('account.dashboard'));
        $u = User::where('phone', '9876501111')->sole();
        $this->assertSame('customer', $u->type->value);
        $this->assertAuthenticatedAs($u);

        $this->post('/logout');
        $this->post(route('login.otp.send'), ['phone' => $this->owner->phone])->assertSessionHasErrors('phone');
    }

    public function test_customer_dashboard_only_shows_own_bookings(): void
    {
        $this->mapsOn(50);
        $this->verifyPhone('9825012345');
        $this->post(route('microsite.store', 'shiv-travels'), $this->trip());
        $mine = Booking::withoutGlobalScopes()->sole();
        $this->post('/logout');

        $this->verifyPhone('9825099999');
        $this->get(route('account.dashboard'))->assertOk()->assertDontSee($mine->booking_number);
    }
}
