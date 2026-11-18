<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:100'],
            'tenant' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $logs = AuditLog::query()->with(['user:id,name,type', 'tenant:id,uuid,name'])
            ->when($filters['event'] ?? null, fn ($q, $e) => $q->where('event', 'like', addcslashes($e, '%_\\').'%'))
            ->when($filters['tenant'] ?? null, fn ($q, $t) => $q->where('tenant_id', $t))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', $d.' 00:00:00'))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest('id')->paginate(30)->withQueryString();

        return view('admin.audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'tenants' => Tenant::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
