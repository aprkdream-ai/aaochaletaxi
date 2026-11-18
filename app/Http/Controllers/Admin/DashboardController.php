<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TenantDocument;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $tenantCounts = Tenant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $stats = [
            'operators_active' => (int) ($tenantCounts[TenantStatus::Active->value] ?? 0),
            'operators_pending' => (int) ($tenantCounts[TenantStatus::Pending->value] ?? 0),
            'operators_suspended' => (int) ($tenantCounts[TenantStatus::Suspended->value] ?? 0),
            'customers' => User::query()->where('type', UserType::Customer)->count(),
            'trials' => Subscription::query()->where('status', SubscriptionStatus::Trialing)->count(),
            'paid' => Subscription::query()->where('status', SubscriptionStatus::Active)->count(),
            'enquiries' => ContactMessage::query()->whereNull('handled_at')->count(),
            'revenue_month' => SubscriptionPayment::query()->where('status', 'paid')->where('paid_at', '>=', now()->startOfMonth())->sum('total'),
            'expiring' => Subscription::query()->whereNull('superseded_by')->whereIn('status', ['trialing', 'active'])->whereBetween('ends_at', [now(), now()->addDays(7)])->count(),
            'tickets' => SupportTicket::query()->where('status', 'open')->count(),
            'kyc_pending' => TenantDocument::query()->where('status', 'pending')->count(),
        ];

        return view('admin.dashboard', [
            'stats' => $stats,
            'pendingTenants' => Tenant::query()->where('status', TenantStatus::Pending)->latest()->limit(5)->get(),
            'recentActivity' => AuditLog::query()->with(['user:id,name', 'tenant:id,name'])->where('action', 'not like', 'auth.%')->latest('id')->limit(8)->get(),
        ]);
    }
}
