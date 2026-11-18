<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    public function test_install_and_remove_cleanly_without_invoices_in_production_mode(): void
    {
        User::factory()->superAdmin()->create();
        $real = Tenant::factory()->create(['name' => 'Real Operator']);
        ContactMessage::create(['name' => 'Real lead', 'phone' => '9876543210', 'message' => 'real enquiry text', 'type' => 'contact']);

        $this->app['env'] = 'production';
        $this->artisan('demo:data', ['action' => 'install', '--force' => true, '--owner-password' => 'Demo@2026', '--driver-password' => 'Drive@2026'])->assertSuccessful();
        $this->app['env'] = 'testing';

        $demo = Tenant::where('email', 'owner@shivtravels.test')->sole();
        $this->assertSame('Shiv Travels (Demo)', $demo->name);
        $this->assertSame('active', $demo->status->value);
        $this->assertSame(0, SubscriptionPayment::withoutGlobalScopes()->count(), 'no invoice numbers consumed');
        $this->assertSame(3, Vehicle::withoutGlobalScopes()->where('tenant_id', $demo->id)->count());

        $this->post(route('login.store'), ['login' => 'owner@shivtravels.test', 'password' => 'Demo@2026'])->assertRedirect(route('operator.dashboard'));
        $this->get(route('operator.vehicles.index'))->assertOk()->assertSee('GJ 01 AB 1234');
        auth()->logout();

        $this->artisan('demo:data', ['action' => 'install', '--force' => true])->assertFailed();

        $this->artisan('demo:data', ['action' => 'remove', '--force' => true])->assertSuccessful();
        $this->assertSame(0, Tenant::withTrashed()->whereIn('email', ['owner@shivtravels.test', 'owner@ganeshcabs.test'])->count());
        $this->assertSame(0, User::withTrashed()->where('phone', '9811122233')->count());
        $this->assertTrue($real->fresh()->exists);
        $this->assertSame(['Real lead'], ContactMessage::pluck('name')->all());
    }
}
