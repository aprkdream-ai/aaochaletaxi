<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KycTest extends TestCase
{
    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->tenant = Tenant::factory()->pending()->create();
        $this->owner = $this->owner($this->tenant);
    }

    private function upload(string $type, ?UploadedFile $file = null)
    {
        return $this->actingAs($this->owner)->post(route('operator.kyc.store'), ['type' => $type, 'file' => $file ?? UploadedFile::fake()->create('pan.pdf', 200, 'application/pdf')]);
    }

    public function test_pending_operator_can_upload_privately(): void
    {
        $this->actingAs($this->owner)->get(route('operator.kyc.index'))->assertOk()->assertSee('PAN card');
        $this->upload('pan_card')->assertSessionHasNoErrors()->assertSessionHas('success');

        $doc = TenantDocument::withoutGlobalScopes()->sole();
        $this->assertSame($this->tenant->id, $doc->tenant_id);
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertStringStartsWith('kyc/'.$this->tenant->uuid.'/', $doc->file_path);
        $this->assertSame('submitted', $this->tenant->fresh()->kyc_status->value);

        $this->actingAs($this->owner)->get(route('operator.kyc.download', $doc))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_rejects_dangerous_or_large_files(): void
    {
        $this->upload('pan_card', UploadedFile::fake()->create('evil.php', 10, 'application/x-php'))->assertSessionHasErrors('file');
        $this->upload('pan_card', UploadedFile::fake()->create('a.html', 10, 'text/html'))->assertSessionHasErrors('file');
        $this->upload('pan_card', UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf'))->assertSessionHasErrors('file');
        $this->upload('passport_photo')->assertSessionHasErrors('type');
        $this->assertSame(0, TenantDocument::withoutGlobalScopes()->count());
    }

    public function test_other_tenants_cannot_access_documents(): void
    {
        $this->upload('pan_card');
        $doc = TenantDocument::withoutGlobalScopes()->sole();
        $intruder = $this->owner(Tenant::factory()->create());

        $this->actingAs($intruder)->get(route('operator.kyc.download', $doc))->assertNotFound();
        $this->actingAs($intruder)->delete(route('operator.kyc.destroy', $doc))->assertNotFound();
        $this->actingAs(User::factory()->customer()->create())->get(route('admin.kyc.download', $doc))->assertForbidden();
        $this->assertSame(1, TenantDocument::withoutGlobalScopes()->count());
    }

    public function test_admin_review_auto_verifies_when_required_docs_approved(): void
    {
        foreach (['pan_card', 'owner_id_proof', 'address_proof'] as $type) {
            $this->upload($type);
        }
        $admin = User::factory()->superAdmin()->create();
        $docs = TenantDocument::withoutGlobalScopes()->get();

        $this->actingAs($admin)->get(route('admin.kyc.download', $docs[0]))->assertOk();
        $this->actingAs($admin)->get(route('admin.tenants.show', $this->tenant))->assertOk()->assertSee('pan.pdf');
        $this->actingAs($admin)->post(route('admin.kyc.review', $docs[0]), ['status' => 'rejected'])->assertSessionHasErrors('review_notes');

        foreach ($docs as $doc) {
            $this->actingAs($admin)->post(route('admin.kyc.review', $doc), ['status' => 'approved'])->assertSessionHas('success');
        }
        $this->assertSame('verified', $this->tenant->fresh()->kyc_status->value);

        // Approved documents cannot be deleted by the operator.
        $this->actingAs($this->owner)->delete(route('operator.kyc.destroy', $docs[0]))->assertSessionHasErrors('document');
        Storage::disk('local')->assertExists($docs[0]->file_path);
    }

    public function test_operator_can_delete_pending_document(): void
    {
        $this->upload('pan_card');
        $doc = TenantDocument::withoutGlobalScopes()->sole();
        $this->actingAs($this->owner)->delete(route('operator.kyc.destroy', $doc))->assertSessionHas('success');
        Storage::disk('local')->assertMissing($doc->file_path);
    }

    public function test_staff_without_settings_permission_cannot_manage_kyc(): void
    {
        $bookingManager = User::factory()->operatorOf($this->tenant, 'booking-manager')->create();
        $this->actingAs($bookingManager)->get(route('operator.kyc.index'))->assertForbidden();
    }
}
