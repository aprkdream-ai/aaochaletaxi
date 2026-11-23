<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\VehicleCategory;
use App\Services\Booking\FareRuleService;
use App\Services\Maps\GoogleMapsService;
use App\Support\India;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuggestTest extends TestCase
{
    private function booking(Tenant $t, string $pickup, string $drop): void
    {
        app(FareRuleService::class)->seedDefaults($t);
        $this->actingAs($this->owner($t))->post(route('operator.bookings.store'), [
            'type' => 'outstation_oneway', 'vehicle_category_id' => VehicleCategory::where('slug', 'sedan')->value('id'),
            'customer_name' => 'Priya Shah', 'customer_phone' => '98250 12345', 'pickup_address' => $pickup, 'drop_address' => $drop,
            'pickup_at' => now()->addDay()->setTime(9, 0)->format('Y-m-d H:i'), 'distance_km' => 260, 'passengers' => 2, 'idempotency_key' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        auth()->logout();
    }

    public function test_state_normalisation(): void
    {
        $this->assertSame('Madhya Pradesh', India::normalizeState(' mp '));
        $this->assertSame('Odisha', India::normalizeState('Orissa'));
        $this->assertSame('Tamil Nadu', India::normalizeState('tamil  nadu'));
        $this->assertNull(India::normalizeState('Atlantis'));
        $this->assertSame('23', India::stateCode('Madhya Pradesh'));
        $this->assertCount(36, India::states());
    }

    public function test_cities_filtered_by_state(): void
    {
        $items = $this->getJson(route('suggest.cities', ['q' => 'ind', 'state' => 'MP']))->assertOk()->json('items');
        $this->assertSame('Indore', $items[0]['value']);
        $this->assertSame('Madhya Pradesh', $items[0]['state']);

        $all = $this->getJson(route('suggest.cities', ['state' => 'Goa']))->json('items');
        $this->assertContains('Panaji', array_column($all, 'value'));
        $this->assertSame([], $this->getJson(route('suggest.cities'))->json('items'));
    }

    public function test_places_fall_back_to_cities_and_never_leak_bookings_to_guests(): void
    {
        $t = Tenant::factory()->create();
        $this->booking($t, 'Shivam Residency, Udaipur', 'Jaipur Airport');

        $items = $this->getJson(route('suggest.places', ['q' => 'udai']))->assertOk()->json('items');
        $this->assertSame('Udaipur', $items[0]['label']);
        $this->assertNotContains('recent', array_column($items, 'source'));
    }

    public function test_operator_sees_only_own_recent_addresses(): void
    {
        $mine = Tenant::factory()->create();
        $other = Tenant::factory()->create();
        $this->booking($mine, 'Shivam Residency, Udaipur', 'Jaipur Airport');
        $this->booking($other, 'Udaipur Palace Hotel', 'Ajmer');

        $items = $this->actingAs($this->owner($mine))->getJson(route('suggest.places', ['q' => 'udaipur']))->json('items');
        $values = array_column($items, 'value');
        $this->assertSame('Shivam Residency, Udaipur', $items[0]['value']);
        $this->assertSame('recent', $items[0]['source']);
        $this->assertNotContains('Udaipur Palace Hotel', $values);
    }

    public function test_google_places_used_when_configured_and_switches_off_on_auth_error(): void
    {
        PlatformSetting::setValue('google_maps_api_key', 'test-key', true);
        Http::fake(['places.googleapis.com/*' => Http::sequence()
            ->push(['suggestions' => [
                ['placePrediction' => ['text' => ['text' => 'Rajwada, Indore, Madhya Pradesh, India'], 'structuredFormat' => ['mainText' => ['text' => 'Rajwada'], 'secondaryText' => ['text' => 'Indore, Madhya Pradesh, India']]]],
            ]])
            ->push(['error' => ['status' => 'PERMISSION_DENIED']], 403)]);

        $items = $this->getJson(route('suggest.places', ['q' => 'rajwada', 'token' => 'abc12345-token']))->json('items');
        $this->assertSame('google', $items[0]['source']);
        $this->assertSame('Rajwada, Indore, Madhya Pradesh, India', $items[0]['value']);
        Http::assertSent(fn ($r) => $r->hasHeader('X-Goog-Api-Key', 'test-key') && $r['includedRegionCodes'] === ['in'] && $r['sessionToken'] === 'abc12345-token');

        $this->getJson(route('suggest.places', ['q' => 'indore']))->assertOk();
        $this->assertTrue(Cache::has('maps:places:off'));
    }

    public function test_free_openstreetmap_suggestions_and_distance_without_google_key(): void
    {
        Http::fake([
            'photon.komoot.io/*' => fn ($r) => Http::response(['features' => [
                str_contains($r['q'], 'Pune')
                    ? ['properties' => ['name' => 'Pune', 'state' => 'Maharashtra', 'countrycode' => 'IN'], 'geometry' => ['coordinates' => [73.8567, 18.5204]]]
                    : ['properties' => ['name' => 'Rajwada Palace', 'city' => 'Indore', 'state' => 'Madhya Pradesh', 'countrycode' => 'IN'], 'geometry' => ['coordinates' => [75.8547, 22.7180]]],
                ['properties' => ['name' => 'Rajwada', 'state' => 'Punjab', 'countrycode' => 'PK'], 'geometry' => ['coordinates' => [74.3, 31.5]]],
            ]]),
            'router.project-osrm.org/*' => Http::response(['code' => 'Ok', 'routes' => [['distance' => 590400, 'duration' => 25200]]]),
        ]);

        $items = $this->getJson(route('suggest.places', ['q' => 'rajwada']))->json('items');
        $this->assertSame('Rajwada Palace, Indore, Madhya Pradesh', $items[0]['value']);
        $this->assertSame('place', $items[0]['source']);
        $this->assertArrayNotHasKey('lat', $items[0]);
        $this->assertNotContains('PK', array_column($items, 'sub'));

        $d = app(GoogleMapsService::class)->distance('Rajwada Indore', 'Pune');
        $this->assertSame(590.4, $d['km']);
        $this->assertSame(588, $d['minutes']); // 420 min × 1.4 Indian-road factor
        Http::assertSent(fn ($r) => str_contains($r->url(), 'router.project-osrm.org/route/v1/driving/75.854700,22.718000;73.856700,18.520400'));

        PlatformSetting::setValue('maps_free', 'off');
        $this->assertFalse(app(GoogleMapsService::class)->isEnabled());
    }

    public function test_free_service_errors_fall_back_to_city_list(): void
    {
        Http::fake(['photon.komoot.io/*' => Http::response('busy', 429)]);
        $items = $this->getJson(route('suggest.places', ['q' => 'shirdi']))->json('items');
        $this->assertSame('city', $items[0]['source']);
        $this->assertTrue(Cache::has('maps:photon:off'));
    }

    public function test_state_fields_are_validated_and_normalised(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);
        $base = ['name' => 'Shree Travels', 'email' => 'a@b.com', 'phone' => '9876543210', 'city' => 'Indore'];

        $this->actingAs($owner)->put(route('operator.profile.update'), $base + ['state' => 'Atlantis'])->assertSessionHasErrors('state');
        $this->actingAs($owner)->put(route('operator.profile.update'), $base + ['state' => 'm.p.'])->assertSessionHasNoErrors();
        $this->assertSame('Madhya Pradesh', $tenant->refresh()->state);
    }
}
