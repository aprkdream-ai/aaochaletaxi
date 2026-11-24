<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\PlatformSetting;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_public_pages_render(): void
    {
        foreach (['home', 'about', 'services', 'partner', 'contact', 'legal.privacy', 'legal.terms', 'legal.refund', 'legal.shipping', 'login', 'register.operator', 'register.customer', 'password.request'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('AaoChale', false);
        }
    }

    public function test_partner_page_lists_seeded_plans(): void
    {
        $this->get(route('partner'))->assertOk()->assertSee('Starter')->assertSee('₹799')->assertSee('₹7,990')->assertSee('Growth')->assertSee('Business')->assertSee('Compare plans');
    }

    public function test_home_has_seo_tags(): void
    {
        $this->get('/')->assertSee('<link rel="canonical"', false)->assertSee('application/ld+json', false)->assertSee('og:title', false);
    }

    public function test_rich_seo_markup(): void
    {
        $this->get('/')
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"Organization"', false)
            ->assertSee('og-image.png', false)
            ->assertSee('site.webmanifest', false)
            ->assertSee('Plans from ₹799/month', false);

        $this->get(route('services'))->assertSee('"@type":"BreadcrumbList"', false);
        $this->get(route('legal.terms'))->assertSee('Terms governing bookings', false);
        $this->get(route('login'))->assertSee('noindex, nofollow', false);
    }

    public function test_static_seo_assets_exist(): void
    {
        foreach (['og-image.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'site.webmanifest', 'favicon.svg', 'robots.txt'] as $file) {
            $this->assertFileExists(public_path($file));
        }
        $this->assertJson(file_get_contents(public_path('site.webmanifest')));
    }

    public function test_route_and_trip_prefill_on_home(): void
    {
        $this->get('/?pickup=Delhi&drop=Agra&trip=airport')
            ->assertSee('value="Delhi"', false)->assertSee('value="Agra"', false)->assertSee("quoteForm('airport')", false);

        // Unknown trip types fall back safely; markup is escaped.
        $this->get('/?trip=evil%27);alert(1)//&pickup=%3Cscript%3E')
            ->assertSee("quoteForm('outstation_oneway')", false)
            ->assertDontSee('<script>alert', false)
            ->assertSee('&lt;script&gt;', false);
    }

    public function test_icons_render_inline_without_icon_font(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"', $html);
        $this->assertStringNotContainsString('bootstrap-icons', $html);
    }

    public function test_sitemap_is_xml(): void
    {
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml')->assertSee('<urlset', false)->assertSee(route('legal.terms'), false);
    }

    public function test_contact_form_stores_message(): void
    {
        $this->post(route('contact.store'), ['name' => 'Asha', 'phone' => '+91 98765 43210', 'message' => 'Need a cab for Jaipur trip'])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertDatabaseHas('contact_messages', ['name' => 'Asha', 'phone' => '9876543210', 'type' => 'contact']);
    }

    public function test_contact_form_validates_phone(): void
    {
        $this->post(route('contact.store'), ['name' => 'X', 'phone' => '12345', 'message' => 'hello there friend'])->assertSessionHasErrors('phone');
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_honeypot_silently_drops_spam(): void
    {
        $this->post(route('contact.store'), ['name' => 'Bot', 'phone' => '9876543210', 'message' => 'spam spam spam', 'website' => 'x'])->assertSessionHas('success');
        $this->assertSame(0, ContactMessage::count());
    }

    public function test_fare_enquiry_is_stored(): void
    {
        $this->post(route('enquiry.store'), [
            'trip_type' => 'airport', 'pickup' => 'Ahmedabad Airport', 'drop' => 'Gandhinagar',
            'travel_date' => now()->addDay()->toDateString(), 'phone' => '9876543210', 'name' => 'Ravi',
        ])->assertSessionHas('success');

        $msg = ContactMessage::sole();
        $this->assertSame('enquiry', $msg->type);
        $this->assertStringContainsString('Ahmedabad Airport', $msg->message);
    }

    public function test_enquiry_rejects_past_date(): void
    {
        $this->post(route('enquiry.store'), [
            'trip_type' => 'local', 'pickup' => 'Pune', 'travel_date' => now()->subDay()->toDateString(), 'phone' => '9876543210', 'name' => 'R',
        ])->assertSessionHasErrors('travel_date');
    }

    public function test_locale_switch_to_hindi(): void
    {
        $this->get(route('locale', 'hi'))->assertRedirect();
        $this->get('/')->assertSee('होम');
        $this->get(route('locale', 'fr'))->assertNotFound();
    }

    public function test_security_headers_present(): void
    {
        $this->get('/')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_unknown_page_returns_404(): void
    {
        $this->get('/no-such-page')->assertNotFound();
    }

    public function test_business_details_shown_on_policy_pages_when_configured(): void
    {
        $this->get(route('legal.shipping'))->assertOk()->assertSee('do not sell or ship any physical goods', false)->assertDontSee('owned and operated by');
        PlatformSetting::setValue('platform_legal_name', 'Test Owner');
        PlatformSetting::setValue('platform_address', "12 Main Road\nIndore 452001");
        foreach (['legal.shipping', 'legal.terms', 'legal.refund', 'legal.privacy', 'contact'] as $r) {
            $this->get(route($r))->assertOk()->assertSee('Test Owner')->assertSee('Indore 452001');
        }
        $this->get(route('home'))->assertSee('Owned &amp; operated by Test Owner', false)->assertSee(route('legal.shipping'), false);
    }

    public function test_turbo_drive_only_on_panels(): void
    {
        $this->actingAs($this->owner())->get(route('operator.dashboard'))->assertOk()
            ->assertSee('data-turbo="true"', false)->assertSee('turbo-cache-control', false)
            ->assertSee('data-turbo-track="reload"', false)->assertDontSee('speculationrules', false);
        $this->get(route('home'))->assertOk()->assertDontSee('data-turbo="true"', false)->assertSee('speculationrules', false);
    }
}
