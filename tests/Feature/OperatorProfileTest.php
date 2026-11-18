<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperatorProfileTest extends TestCase
{
    public function test_update_profile_with_logo(): void
    {
        Storage::fake('public');
        $tenant = Tenant::factory()->create();
        $owner = $this->owner($tenant);

        $this->actingAs($owner)->get(route('operator.profile.edit'))->assertOk();
        $this->actingAs($owner)->put(route('operator.profile.update'), [
            'name' => 'Shree Travels', 'legal_name' => 'Shree Travels Pvt Ltd', 'email' => 'a@b.com', 'phone' => '9876543210',
            'gstin' => '24abcde1234f1z5', 'pan' => 'abcde1234f', 'city' => 'Surat', 'state' => 'Gujarat', 'pincode' => '395003',
            'primary_color' => '#112233', 'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
            'tenant_id' => 999, 'status' => 'active', 'slug' => 'hijack',
        ])->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertSame('24ABCDE1234F1Z5', $tenant->gstin);
        $this->assertSame('ABCDE1234F', $tenant->pan);
        $this->assertNotSame('hijack', $tenant->slug);
        Storage::disk('public')->assertExists($tenant->logo_path);
    }

    public function test_validation_rejects_bad_values_and_svg_logo(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        $this->actingAs($owner)->put(route('operator.profile.update'), [
            'name' => 'X', 'email' => 'bad', 'phone' => '123', 'gstin' => 'BADGST', 'pan' => '1234', 'city' => '', 'pincode' => '12', 'primary_color' => 'red',
            'logo' => UploadedFile::fake()->create('x.svg', 5, 'image/svg+xml'),
        ])->assertSessionHasErrors(['name', 'email', 'phone', 'gstin', 'pan', 'city', 'pincode', 'primary_color', 'logo']);
    }

    public function test_pending_operator_can_reach_onboarding_pages_but_not_dashboard(): void
    {
        $owner = $this->owner(Tenant::factory()->pending()->create());
        $this->actingAs($owner)->get(route('operator.status'))->assertOk()->assertSee('Upload KYC documents');
        $this->actingAs($owner)->get(route('operator.profile.edit'))->assertOk();
        $this->actingAs($owner)->get(route('operator.support.create'))->assertOk();
        $this->actingAs($owner)->get(route('operator.dashboard'))->assertRedirect(route('operator.status'));
    }

    public function test_accountant_cannot_edit_profile_but_can_use_support(): void
    {
        $accountant = User::factory()->operatorOf(Tenant::factory()->create(), 'accountant')->create();
        $this->actingAs($accountant)->get(route('operator.profile.edit'))->assertForbidden();
        $this->actingAs($accountant)->get(route('operator.subscription'))->assertForbidden();
        $this->actingAs($accountant)->get(route('operator.support.index'))->assertOk();
    }
}
