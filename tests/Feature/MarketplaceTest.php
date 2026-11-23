<?php

namespace Tests\Feature;

use App\Enums\BusinessType;
use App\Enums\KycStatus;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\MarketplaceLead;
use App\Models\MarketplaceLeadOffer;
use App\Models\PlatformSetting;
use App\Models\PlatformSettlement;
use App\Models\Tenant;
use App\Models\TenantServiceCity;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Notifications\MarketplaceLeadOffered;
use App\Services\Booking\BookingService;
use App\Services\Booking\FareRuleService;
use App\Services\Marketplace\MarketplaceService;
use App\Services\Messaging\OtpService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    private int $sedan;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        PlatformSetting::setValue('maps_free', 'off');
        PlatformSetting::setValue('google_maps_api_key', 'AIza-test', true);
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'OK', 'origin_addresses' => ['A'], 'destination_addresses' => ['B'],
            'rows' => [['elements' => [['status' => 'OK', 'distance' => ['value' => 100000], 'duration' => ['value' => 7200]]]]]])]);
        $this->sedan = VehicleCategory::where('slug', 'sedan')->whereNull('tenant_id')->value('id');
    }

    private function operator(string $name, array $cities = ['Ahmedabad'], array $o = []): Tenant
    {
        $t = Tenant::factory()->businessType($o['type'] ?? BusinessType::Taxi)->create(['name' => $name, 'slug' => Str::slug($name)]);
        $t->forceFill(['marketplace_enabled' => $o['enabled'] ?? true, 'kyc_status' => $o['kyc'] ?? KycStatus::NotSubmitted])->save();
        app(FareRuleService::class)->seedDefaults($t);
        foreach ($cities as $c) {
            $t->serviceCities()->create(['city' => $c]);
        }

        return $t;
    }

    private function query(array $o = []): array
    {
        return $o + ['from_city' => 'Ahmedabad', 'to_city' => 'Vadodara', 'type' => 'outstation_oneway', 'vehicle_category_id' => $this->sedan,
            'pickup_at' => now()->addDays(2)->setTime(8, 0)->format('Y-m-d H:i')];
    }

    private function verifyPhone(string $phone = '9825012345'): void
    {
        $this->postJson(route('marketplace.otp.send'), ['phone' => $phone])->assertOk();
        $this->postJson(route('marketplace.otp.verify'), ['phone' => $phone, 'otp' => OtpService::$lastCode, 'name' => 'Priya'])->assertOk();
    }

    // ---------- Listing rules & search ----------

    public function test_search_lists_only_eligible_operators_ranked_with_server_fares(): void
    {
        $this->operator('Alpha Cabs');
        $this->operator('Verified Tours', ['Ahmedabad', 'Gandhinagar'], ['kyc' => KycStatus::Verified]);
        $this->operator('Hidden Cabs', ['Ahmedabad'], ['enabled' => false]);
        $this->operator('Surat Only', ['Surat']);
        $this->operator('Fleet Corp', ['Ahmedabad'], ['type' => BusinessType::Fleet]);
        $suspended = $this->operator('Suspended Cabs');
        $suspended->forceFill(['status' => 'suspended'])->save();
        $noPage = $this->operator('No Page Cabs');
        $noPage->forceFill(['settings' => ['booking_page_enabled' => false]])->save();

        $res = $this->get(route('marketplace.search', $this->query()))->assertOk()
            ->assertSee('Alpha Cabs')->assertSee('Verified Tours')->assertSee('about 100 km')
            ->assertDontSee('Hidden Cabs')->assertDontSee('Surat Only')->assertDontSee('Fleet Corp')->assertDontSee('Suspended Cabs')->assertDontSee('No Page Cabs');
        $this->assertLessThan(strpos($res->getContent(), 'Alpha Cabs'), strpos($res->getContent(), 'Verified Tours')); // verified ranks first

        $found = app(MarketplaceService::class)->search($this->query());
        $this->assertSame(100.0, $found['km']);
        $this->assertCount(2, $found['results']);
        $this->assertNotNull($found['results'][0]->estimate); // priced by each operator's own fare rule

        $this->get(route('marketplace.search', $this->query(['pickup_at' => now()->format('Y-m-d H:i')])))->assertSessionHasErrors('pickup_at');
        $this->get(route('marketplace.search', $this->query(['vehicle_category_id' => 999999])))->assertSessionHasErrors('vehicle_category_id');
    }

    public function test_city_pages_and_sitemap(): void
    {
        $this->operator('Alpha Cabs', ['Ahmedabad', 'Gandhinagar']);
        $this->get(route('marketplace.city', 'gandhinagar'))->assertOk()->assertSee('Taxi operators in Gandhinagar')->assertSee('Alpha Cabs');
        $this->get(route('marketplace.city', 'nowhere'))->assertNotFound();
        $this->get(route('sitemap'))->assertOk()->assertSee(route('marketplace.city', 'ahmedabad'))->assertSee(route('marketplace.search'));
        $this->get(route('marketplace.search'))->assertOk()->assertSee('Gandhinagar');
    }

    // ---------- Operator profile ----------

    public function test_operator_opt_in_and_service_cities(): void
    {
        $t = Tenant::factory()->create(['name' => 'Shiv Travels', 'city' => 'Ahmedabad', 'email' => 'shiv@example.com', 'phone' => '9876500001']);
        $owner = $this->owner($t);
        $base = ['name' => 'Shiv Travels', 'email' => 'shiv@example.com', 'phone' => '9876500001', 'city' => 'Ahmedabad', 'booking_page_enabled' => 1];

        $this->actingAs($owner)->put(route('operator.profile.update'), $base + ['marketplace_enabled' => 1, 'service_cities' => ''])->assertSessionHasErrors('service_cities');
        $this->actingAs($owner)->put(route('operator.profile.update'), $base + ['marketplace_enabled' => 1, 'service_cities' => "Ahmedabad, gandhinagar\nAhmedabad"])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertTrue($t->marketplace_enabled);
        $this->assertSame(['ahmedabad', 'gandhinagar'], TenantServiceCity::where('tenant_id', $t->id)->orderBy('city_key')->pluck('city_key')->all());

        $this->actingAs($owner)->put(route('operator.profile.update'), $base + ['service_cities' => 'Vadodara'])->assertSessionHasNoErrors();
        $this->assertFalse($t->fresh()->marketplace_enabled);
        $this->assertSame(['vadodara'], TenantServiceCity::where('tenant_id', $t->id)->pluck('city_key')->all());
        $this->actingAs($owner)->get(route('operator.profile.edit'))->assertOk()->assertSee('List my business on AaoChaleTaxi marketplace');

        $fleetOwner = $this->owner(Tenant::factory()->businessType(BusinessType::Fleet)->create());
        $this->actingAs($fleetOwner)->get(route('operator.profile.edit'))->assertOk()->assertDontSee('List my business on AaoChaleTaxi marketplace');
    }

    // ---------- Direct booking via marketplace → commission ----------

    public function test_direct_booking_from_marketplace_carries_commission(): void
    {
        PlatformSetting::setValue('marketplace_commission_percent', '10');
        $t = $this->operator('Alpha Cabs');

        $this->get(route('marketplace.go', ['alpha-cabs'] + $this->query()))->assertRedirect(route('microsite.show', 'alpha-cabs'));
        $this->get(route('microsite.show', 'alpha-cabs'))->assertOk()->assertSee('Vadodara');

        $this->postJson(route('microsite.otp.send', 'alpha-cabs'), ['phone' => '9825012345'])->assertOk();
        $this->postJson(route('microsite.otp.verify', 'alpha-cabs'), ['phone' => '9825012345', 'otp' => OtpService::$lastCode, 'name' => 'Priya'])->assertOk();
        $this->post(route('microsite.store', 'alpha-cabs'), ['type' => 'outstation_oneway', 'vehicle_category_id' => $this->sedan, 'pickup_address' => 'Ahmedabad', 'drop_address' => 'Vadodara',
            'pickup_at' => now()->addDays(2)->setTime(8, 0)->format('Y-m-d H:i'), 'customer_name' => 'Priya', 'customer_phone' => '9825012345', 'idempotency_key' => (string) Str::uuid()])
            ->assertSessionHasNoErrors();

        $b = Booking::withoutGlobalScopes()->where('tenant_id', $t->id)->sole();
        $this->assertSame('marketplace', $b->source);
        $this->assertSame('10.00', $b->commission_percent);

        $this->get(route('marketplace.go', 'surat-cabs'))->assertNotFound();
    }

    // ---------- Quote request broadcast ----------

    public function test_quote_request_broadcasts_to_top_operators_and_first_accept_wins(): void
    {
        PlatformSetting::setValue('marketplace_broadcast_count', '2');
        $a = $this->operator('Alpha Cabs', ['Ahmedabad'], ['kyc' => KycStatus::Verified]);
        $b = $this->operator('Beta Cabs', ['Ahmedabad'], ['kyc' => KycStatus::Verified]);
        $c = $this->operator('Gamma Cabs');
        $ownerA = $this->owner($a);
        $ownerB = $this->owner($b);

        $payload = $this->query() + ['customer_name' => 'Priya', 'customer_phone' => '9825012345', 'pickup_address' => 'Satellite, Ahmedabad', 'passengers' => 2];
        $this->post(route('marketplace.quote'), $payload)->assertSessionHasErrors('customer_phone'); // phone not verified
        $this->verifyPhone();
        $this->post(route('marketplace.quote'), $payload)->assertSessionHasNoErrors();

        $lead = MarketplaceLead::sole();
        $this->assertSame('open', $lead->status);
        $this->assertSame([$a->id, $b->id], MarketplaceLeadOffer::withoutGlobalScopes()->orderBy('tenant_id')->pluck('tenant_id')->all()); // top 2 (verified)
        Notification::assertSentTo($ownerA, MarketplaceLeadOffered::class);
        $this->get(route('marketplace.lead', $lead))->assertOk()->assertSee('Waiting for an operator to accept');

        // Operators see only their own offer; no customer phone before accepting.
        $this->actingAs($ownerA)->get(route('operator.leads.index'))->assertOk()->assertSee('Vadodara')->assertDontSee('9825012345');
        $offerB = MarketplaceLeadOffer::withoutGlobalScopes()->where('tenant_id', $b->id)->sole();
        $this->actingAs($ownerA)->post(route('operator.leads.accept', $offerB))->assertNotFound();

        $offerA = MarketplaceLeadOffer::withoutGlobalScopes()->where('tenant_id', $a->id)->sole();
        $this->actingAs($ownerA)->post(route('operator.leads.accept', $offerA))->assertRedirect();
        $booking = Booking::withoutGlobalScopes()->where('tenant_id', $a->id)->sole();
        $this->assertSame('marketplace', $booking->source);
        $this->assertSame($lead->id, $booking->marketplace_lead_id);
        $this->assertNotNull($booking->commission_percent);
        $this->assertSame('accepted', $lead->fresh()->status);
        $this->assertSame('lost', $offerB->fresh()->status);

        // Second operator is too late.
        $this->actingAs($ownerB)->post(route('operator.leads.accept', $offerB))->assertSessionHasErrors('lead');
        $this->assertSame(0, Booking::withoutGlobalScopes()->where('tenant_id', $b->id)->count());
        $this->get(route('marketplace.lead', $lead))->assertOk()->assertSee('Alpha Cabs accepted your request')->assertSee($booking->booking_number);
        $this->assertSame(0, MarketplaceLeadOffer::withoutGlobalScopes()->where('tenant_id', $c->id)->count());
    }

    public function test_accept_is_serialised_by_lead_lock(): void
    {
        $a = $this->operator('Alpha Cabs');
        $b = $this->operator('Beta Cabs');
        $this->verifyPhone();
        $this->post(route('marketplace.quote'), $this->query() + ['customer_name' => 'P', 'customer_phone' => '9825012345'])->assertSessionHasNoErrors();
        $service = app(MarketplaceService::class);
        $ctx = app(TenantContext::class);

        $offers = MarketplaceLeadOffer::withoutGlobalScopes()->get()->keyBy('tenant_id');
        $ctx->runAs($a, fn () => $service->accept($offers[$a->id], $this->owner($a)));
        $this->expectException(ValidationException::class);
        $ctx->runAs($b, fn () => $service->accept($offers[$b->id], $this->owner($b)));
    }

    public function test_decline_all_and_expiry_close_requests(): void
    {
        $a = $this->operator('Alpha Cabs');
        $this->verifyPhone();
        $this->post(route('marketplace.quote'), $this->query() + ['customer_name' => 'P', 'customer_phone' => '9825012345']);
        $lead = MarketplaceLead::sole();
        $this->actingAs($this->owner($a))->post(route('operator.leads.decline', MarketplaceLeadOffer::withoutGlobalScopes()->sole()))->assertSessionHas('success');
        $this->assertSame('expired', $lead->fresh()->status);

        $this->post(route('marketplace.quote'), $this->query() + ['customer_name' => 'P', 'customer_phone' => '9825012345']);
        $lead2 = MarketplaceLead::latest('id')->first();
        $this->travel(3)->hours();
        $this->artisan('marketplace:expire-leads')->assertSuccessful();
        $this->assertSame('expired', $lead2->fresh()->status);
        $this->assertSame('expired', MarketplaceLeadOffer::withoutGlobalScopes()->where('marketplace_lead_id', $lead2->id)->value('status'));
        $this->get(route('marketplace.lead', $lead2))->assertOk()->assertSee('No operator accepted in time');
    }

    public function test_quote_with_no_operator_fails_cleanly(): void
    {
        $this->verifyPhone();
        $this->post(route('marketplace.quote'), $this->query(['from_city' => 'Nowhere']) + ['customer_name' => 'P', 'customer_phone' => '9825012345'])->assertSessionHasErrors('from_city');
        $this->assertSame(0, MarketplaceLead::count());
    }

    // ---------- Commission & settlement ----------

    public function test_commission_fixed_at_completion_and_deducted_in_settlement(): void
    {
        PlatformSetting::setValue('marketplace_commission_percent', '10');
        $t = $this->operator('Alpha Cabs');
        $booking = app(TenantContext::class)->runAs($t, function () use ($t) {
            $b = app(BookingService::class)->create($t, ['source' => 'marketplace', 'type' => 'outstation_oneway', 'vehicle_category_id' => $this->sedan,
                'customer_name' => 'P', 'customer_phone' => '9825012345', 'pickup_address' => 'A', 'drop_address' => 'B', 'pickup_at' => now()->addHours(2), 'distance_km' => 100,
                'passengers' => 1], [], [], null, null, true);
            $b->forceFill(['commission_percent' => '10.00'])->save();

            return $b;
        });
        $owner = $this->owner($t);
        $v = app(TenantContext::class)->runAs($t, fn () => Vehicle::create(['registration_number' => 'GJ01AB1234', 'vehicle_category_id' => $this->sedan, 'make' => 'M', 'model' => 'D',
            'fuel_type' => 'diesel', 'seats' => 4, 'ownership' => 'owned', 'status' => 'active', 'is_available' => true, 'insurance_expiry' => now()->addYear(),
            'permit_expiry' => now()->addYear(), 'fitness_expiry' => now()->addYear(), 'puc_expiry' => now()->addYear()]));
        $d = app(TenantContext::class)->runAs($t, fn () => Driver::create(['name' => 'D', 'phone' => '9811122233', 'licence_number' => 'L1', 'licence_expiry' => now()->addYear(),
            'pay_type' => 'monthly', 'status' => 'active', 'is_available' => true]));
        $this->actingAs($owner)->post(route('operator.bookings.confirm', $booking));
        $this->actingAs($owner)->post(route('operator.bookings.assign', $booking), ['vehicle_id' => $v->id, 'driver_id' => $d->id])->assertSessionHas('success');
        preg_match('/OTP: (\d{4})/', session('success'), $m);
        $this->actingAs($owner)->post(route('operator.bookings.start', $booking), ['otp' => $m[1], 'start_km' => 1000]);
        $this->actingAs($owner)->post(route('operator.bookings.complete', $booking), ['end_km' => 1100])->assertSessionHasNoErrors();

        $booking->refresh();
        $taxable = (string) $booking->final_snapshot['taxable'];
        $this->assertSame(bcdiv(bcmul($taxable, '10', 4), '100', 2), $booking->commission_amount);

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get(route('admin.finance.settlements'))->assertOk()->assertSee('Alpha Cabs')->assertSee('operator owes');
        $this->actingAs($admin)->post(route('admin.finance.settle', $t), ['method' => 'upi', 'reference' => 'UTR9', 'paid_on' => now()->toDateString()])->assertSessionHas('success');
        $s = PlatformSettlement::sole();
        $this->assertSame($booking->commission_amount, $s->commission);
        $this->assertSame('-'.$booking->commission_amount, $s->net);
        $this->assertSame($s->id, $booking->fresh()->commission_settlement_id);
        $this->actingAs($admin)->post(route('admin.finance.settle', $t), ['method' => 'upi', 'reference' => 'UTR10', 'paid_on' => now()->toDateString()])->assertSessionHasErrors('settlement');
    }
}
