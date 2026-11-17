<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_public_pages_render(): void
    {
        foreach (['home', 'about', 'services', 'partner', 'contact', 'legal.privacy', 'legal.terms', 'legal.refund', 'login', 'register.operator', 'register.customer', 'password.request'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('AaoChale', false);
        }
    }

    public function test_partner_page_lists_seeded_plans(): void
    {
        $this->get(route('partner'))->assertOk()->assertSee('Starter')->assertSee('₹799.00')->assertSee('Growth')->assertSee('Business');
    }

    public function test_home_has_seo_tags(): void
    {
        $this->get('/')->assertSee('<link rel="canonical"', false)->assertSee('application/ld+json', false)->assertSee('og:title', false);
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
}
