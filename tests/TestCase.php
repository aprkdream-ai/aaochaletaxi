<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = PlatformSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests(); // never call real third-party APIs (Google, OpenStreetMap, Razorpay…) from tests
    }

    protected function owner(?Tenant $tenant = null): User
    {
        $tenant ??= Tenant::factory()->create();

        return User::factory()->operatorOf($tenant, 'owner')->create();
    }
}
