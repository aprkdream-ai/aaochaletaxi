<?php

namespace App\Services;

use App\Models\FleetDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Vehicle / driver documents on the private disk; optional expiry date also updates the owner record. */
class FleetDocumentService
{
    public const DISK = 'local';

    /** Document type → column on the parent that mirrors its expiry. */
    private const MIRROR = [
        'vehicle' => ['insurance' => 'insurance_expiry', 'permit' => 'permit_expiry', 'fitness' => 'fitness_expiry', 'puc' => 'puc_expiry', 'road_tax' => 'road_tax_expiry'],
        'driver' => ['licence' => 'licence_expiry'],
    ];

    public function __construct(private AuditLogger $audit) {}

    public function upload(Model $owner, string $kind, UploadedFile $file, string $type, ?string $number, ?string $expiresOn, User $user): FleetDocument
    {
        $path = $file->storeAs("fleet/{$kind}s/{$owner->getKey()}", Str::random(40).'.'.$file->guessExtension(), self::DISK);

        try {
            return DB::transaction(function () use ($owner, $kind, $file, $type, $number, $expiresOn, $user, $path) {
                $doc = new FleetDocument([
                    'type' => $type, 'number' => $number, 'expires_on' => $expiresOn, 'file_path' => $path,
                    'original_name' => Str::limit($file->getClientOriginalName(), 190, ''), 'mime' => $file->getMimeType(), 'size' => $file->getSize(),
                ]);
                $doc->uploaded_by = $user->id;
                $owner->documents()->save($doc);

                if ($expiresOn && ($column = self::MIRROR[$kind][$type] ?? null)) {
                    $owner->forceFill([$column => $expiresOn])->save();
                }
                $this->audit->log("$kind.document_uploaded", $owner, [], ['type' => $type, 'expires_on' => $expiresOn]);

                return $doc;
            });
        } catch (\Throwable $e) {
            Storage::disk(self::DISK)->delete($path);
            throw $e;
        }
    }

    public function delete(FleetDocument $doc): void
    {
        $doc->delete();
        Storage::disk(self::DISK)->delete($doc->file_path);
        $this->audit->log('fleet.document_deleted', $doc, ['type' => $doc->type], []);
    }

    public function stream(FleetDocument $doc): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISK)->exists($doc->file_path), 404);

        return Storage::disk(self::DISK)->response($doc->file_path, Str::slug($doc->type).'-'.$doc->id.'.'.pathinfo($doc->file_path, PATHINFO_EXTENSION), [
            'Content-Type' => $doc->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
        ], 'inline');
    }
}
