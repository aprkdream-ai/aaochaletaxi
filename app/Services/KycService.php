<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Enums\KycStatus;
use App\Models\Tenant;
use App\Models\TenantDocument;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** KYC documents live on the private `local` disk and are only streamed through authorised routes. */
class KycService
{
    public const DISK = 'local';

    public const MAX_KB = 5120;

    public const MIMES = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    public function __construct(private TenantContext $context, private AuditLogger $audit) {}

    public function upload(Tenant $tenant, UploadedFile $file, DocumentType $type, ?string $number, User $user): TenantDocument
    {
        $path = $file->storeAs('kyc/'.$tenant->uuid, Str::random(40).'.'.$file->guessExtension(), self::DISK);

        try {
            return DB::transaction(function () use ($tenant, $file, $type, $number, $user, $path) {
                $doc = $this->context->runAs($tenant, function () use ($file, $type, $number, $user, $path) {
                    $d = new TenantDocument([
                        'type' => $type, 'number' => $number, 'file_path' => $path,
                        'original_name' => Str::limit($file->getClientOriginalName(), 190, ''),
                        'mime' => $file->getMimeType(), 'size' => $file->getSize(),
                    ]);
                    $d->status = DocumentStatus::Pending;
                    $d->uploaded_by = $user->id;
                    $d->save();

                    return $d;
                });

                if (in_array($tenant->kyc_status, [KycStatus::NotSubmitted, KycStatus::Rejected], true)) {
                    $tenant->kyc_status = KycStatus::Submitted;
                    $tenant->save();
                }

                $this->audit->log('kyc.document_uploaded', $doc, [], ['type' => $type->value], $tenant->id);

                return $doc;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }
    }

    public function delete(TenantDocument $doc): void
    {
        if ($doc->status === DocumentStatus::Approved) {
            throw ValidationException::withMessages(['document' => __('Approved documents cannot be deleted. Contact support to replace them.')]);
        }

        DB::transaction(function () use ($doc) {
            $this->audit->log('kyc.document_deleted', $doc, ['type' => $doc->type->value], [], $doc->tenant_id);
            $doc->delete();
        });
        Storage::disk(self::DISK)->delete($doc->file_path);
    }

    public function review(TenantDocument $doc, DocumentStatus $status, ?string $notes, User $admin): void
    {
        if ($status === DocumentStatus::Rejected && blank($notes)) {
            throw ValidationException::withMessages(['review_notes' => __('Please give a reason for rejection.')]);
        }

        DB::transaction(function () use ($doc, $status, $notes, $admin) {
            $old = ['status' => $doc->status->value];
            $doc->status = $status;
            $doc->review_notes = $notes;
            $doc->reviewed_by = $admin->id;
            $doc->reviewed_at = now();
            $doc->save();
            $this->audit->log('kyc.document_reviewed', $doc, $old, ['status' => $status->value, 'notes' => $notes], $doc->tenant_id);

            // Auto-verify KYC once every required document type has an approved document.
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($doc->tenant_id);
            $approvedTypes = TenantDocument::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->where('status', DocumentStatus::Approved->value)->pluck('type')->map(fn ($t) => $t->value)->unique();
            $required = collect(DocumentType::required())->map(fn ($t) => $t->value);

            if ($required->diff($approvedTypes)->isEmpty() && $tenant->kyc_status !== KycStatus::Verified) {
                $tenant->kyc_status = KycStatus::Verified;
                $tenant->save();
                $this->audit->log('tenant.kyc_updated', $tenant, [], ['kyc_status' => 'verified', 'auto' => true], $tenant->id);
            }
        });
    }

    public function stream(TenantDocument $doc): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($doc->file_path), 404);

        $ext = pathinfo($doc->file_path, PATHINFO_EXTENSION);
        $name = Str::slug($doc->type->value).'-'.$doc->id.'.'.$ext;

        return Storage::disk(self::DISK)->response($doc->file_path, $name, [
            'Content-Type' => $doc->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], 'inline');
    }

    /** @return array<string, string> */
    public static function requiredStatus(Tenant $tenant): array
    {
        $docs = TenantDocument::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
        $out = [];
        foreach (DocumentType::required() as $type) {
            $forType = $docs->where('type', $type);
            $out[$type->value] = match (true) {
                $forType->contains('status', DocumentStatus::Approved) => 'approved',
                $forType->contains('status', DocumentStatus::Pending) => 'pending',
                $forType->contains('status', DocumentStatus::Rejected) => 'rejected',
                default => 'missing',
            };
        }

        return $out;
    }
}
