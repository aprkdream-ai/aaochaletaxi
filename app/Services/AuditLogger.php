<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    private const HIDDEN = ['password', 'remember_token', 'token', 'secret'];

    public function log(string $event, ?Model $subject = null, array $old = [], array $new = [], ?int $tenantId = null): AuditLog
    {
        $request = request();
        $user = $request?->user();

        return AuditLog::create([
            'tenant_id' => $tenantId ?? $subject?->getAttribute('tenant_id') ?? ($subject instanceof Tenant ? $subject->id : $user?->tenant_id),
            'user_id' => $user?->id,
            'event' => $event,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $this->clean($old) ?: null,
            'new_values' => $this->clean($new) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
        ]);
    }

    private function clean(array $values): array
    {
        return array_diff_key($values, array_flip(self::HIDDEN));
    }
}
