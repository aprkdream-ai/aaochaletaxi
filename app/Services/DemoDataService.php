<?php

namespace App\Services;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\Branch;
use App\Models\ContactMessage;
use App\Models\Driver;
use App\Models\FleetDocument;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleExpense;
use App\Models\VehicleMaintenance;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Creates (and removes) a clearly-labelled demo dataset for trying the product.
 * Production-safe: no invoices are issued (keeps GST invoice numbering gap-free) and no notifications are sent.
 */
class DemoDataService
{
    public const MARKER = 'demo_data';

    public const ACTIVE_EMAIL = 'owner@shivtravels.test';

    public const PENDING_EMAIL = 'owner@ganeshcabs.test';

    public function __construct(
        private TenantOnboardingService $onboarding,
        private TenantAdminService $tenants,
        private SubscriptionService $subscriptions,
        private SupportTicketService $support,
        private DriverService $drivers,
        private TenantContext $context,
    ) {}

    public function exists(): bool
    {
        return Tenant::withTrashed()->whereIn('email', [self::ACTIVE_EMAIL, self::PENDING_EMAIL])->exists();
    }

    /**
     * @param  array{owner:string, driver:string}  $passwords
     * @return array<string, string> created logins
     */
    public function install(User $admin, array $passwords, bool $withInvoice, string $suffix = ''): array
    {
        if ($this->exists()) {
            throw new RuntimeException('Demo data already exists. Remove it first.');
        }
        foreach (['9876500001', '9876500002', '9811122233', '9811122244'] as $phone) {
            if (User::withTrashed()->where('phone', $phone)->exists()) {
                throw new RuntimeException("Phone $phone is already used by a real account; demo data not installed.");
            }
        }

        Notification::fake(); // demo actions must not e-mail / SMS anyone
        $enquiryIds = [];

        DB::transaction(function () use ($admin, $passwords, $withInvoice, $suffix, &$enquiryIds) {
            $owner = $this->onboarding->register(['business_name' => 'Shiv Travels'.$suffix, 'city' => 'Ahmedabad', 'state' => 'Gujarat', 'name' => 'Ramesh Patel', 'email' => self::ACTIVE_EMAIL, 'phone' => '9876500001', 'password' => $passwords['owner']]);
            $tenant = $owner->tenant;
            $tenant->forceFill(['address_line' => 'CG Road, Navrangpura', 'pincode' => '380009', 'legal_name' => 'Shiv Travels'.$suffix])->save();
            $this->tenants->approve($tenant, $admin);

            $this->subscriptions->activate($tenant, SubscriptionPlan::where('code', 'growth')->firstOrFail(), 'monthly', 'now', $admin,
                $withInvoice ? ['amount' => 1499, 'method' => 'upi', 'reference' => 'UPI-DEMO-1001'] : null, 'Demo data');

            $ticket = $this->support->open($tenant, $owner, 'How do I add my Innova Crysta?', TicketCategory::Technical, TicketPriority::Normal, 'I want to add a 7-seater to my fleet. Where do I do that?');
            $this->support->reply($ticket, $admin, 'Hi Ramesh! Go to Fleet → Vehicles → Add vehicle and choose the MUV category for the Crysta.');

            $this->context->runAs($tenant, function () use ($owner, $tenant, $passwords) {
                $cat = fn ($slug) => VehicleCategory::whereNull('tenant_id')->where('slug', $slug)->value('id');
                $branch = Branch::create(['name' => 'Ahmedabad HO', 'code' => 'AMD', 'city' => 'Ahmedabad', 'is_head_office' => true, 'is_active' => true]);
                foreach ([
                    ['GJ01AB1234', 'sedan', 'Maruti Suzuki', 'Dzire', 2023, 'cng', 4, 'active', true, 9],
                    ['GJ01CD5678', 'muv', 'Toyota', 'Innova Crysta', 2022, 'diesel', 7, 'active', true, 120],
                    ['GJ18EF9012', 'suv', 'Mahindra', 'XUV700', 2024, 'diesel', 7, 'maintenance', false, 300],
                ] as [$reg, $c, $make, $model, $year, $fuel, $seats, $status, $avail, $insDays]) {
                    $v = Vehicle::create([
                        'registration_number' => $reg, 'vehicle_category_id' => $cat($c), 'branch_id' => $branch->id, 'make' => $make, 'model' => $model, 'year' => $year,
                        'fuel_type' => $fuel, 'seats' => $seats, 'is_ac' => true, 'ownership' => 'owned', 'status' => $status, 'is_available' => $avail,
                        'insurance_expiry' => now()->addDays($insDays), 'permit_expiry' => now()->addMonths(8), 'fitness_expiry' => now()->addMonths(14), 'puc_expiry' => now()->addMonths(4), 'odometer_km' => 42000,
                    ]);
                    $e = new VehicleExpense(['vehicle_id' => $v->id, 'expense_date' => now()->subDays(2), 'category' => 'fuel', 'amount' => 2400, 'payment_method' => 'upi']);
                    $e->recorded_by = $owner->id;
                    $e->save();
                }
                $m = new VehicleMaintenance(['vehicle_id' => Vehicle::where('registration_number', 'GJ18EF9012')->value('id'), 'service_date' => now()->subDay(), 'type' => 'repair', 'description' => 'Clutch plate replacement', 'cost' => 8500, 'odometer_km' => 42000, 'vendor' => 'Mahindra Service, SG Highway']);
                $m->recorded_by = $owner->id;
                $m->save();

                $this->drivers->create($tenant, ['name' => 'Suresh Yadav', 'phone' => '9811122233', 'licence_number' => 'GJ0120190012345', 'licence_type' => 'LMV-TR', 'licence_expiry' => now()->addDays(20), 'pay_type' => 'monthly', 'pay_amount' => 18000, 'status' => 'active', 'is_available' => true, 'branch_id' => $branch->id, 'joined_on' => now()->subYear()], $passwords['driver']);
                $this->drivers->create($tenant, ['name' => 'Mahesh Thakor', 'phone' => '9811122244', 'licence_number' => 'GJ0120180098765', 'licence_type' => 'LMV-TR', 'licence_expiry' => now()->addYears(4), 'pay_type' => 'per_trip', 'pay_amount' => 600, 'status' => 'active', 'is_available' => true, 'branch_id' => $branch->id], null);
            });

            $this->onboarding->register(['business_name' => 'Ganesh Cabs'.$suffix, 'city' => 'Pune', 'state' => 'Maharashtra', 'name' => 'Sunil Joshi', 'email' => self::PENDING_EMAIL, 'phone' => '9876500002', 'password' => $passwords['owner']]);

            foreach ([
                ['Priya Shah', '9825012345', 'enquiry', 'Fare quote: Outstation one-way', "Trip: Outstation one-way\nPickup: Ahmedabad\nDrop: Udaipur\nDate: ".now()->addDays(3)->toDateString()],
                ['Arjun Mehta', '9898012345', 'enquiry', 'Fare quote: Airport pickup / drop', "Trip: Airport pickup / drop\nPickup: SVPI Airport\nDrop: Gandhinagar\nDate: ".now()->addDay()->toDateString()],
                ['Kiran Desai', '9712012345', 'partner', 'Fleet of 12 cars', 'We run 12 cars in Vadodara. Please share a demo.'],
            ] as [$name, $phone, $type, $subject, $message]) {
                $enquiryIds[] = ContactMessage::create(['name' => $name.$suffix, 'phone' => $phone, 'type' => $type, 'subject' => $subject, 'message' => $message])->id;
            }
        });

        PlatformSetting::setValue(self::MARKER, json_encode(['enquiries' => $enquiryIds, 'installed_at' => now()->toIso8601String()]));

        return [
            'Operator (active, Growth plan)' => self::ACTIVE_EMAIL.' / '.$passwords['owner'],
            'Operator (pending approval)' => self::PENDING_EMAIL.' / '.$passwords['owner'],
            'Driver app' => '9811122233 / '.$passwords['driver'],
        ];
    }

    /** Permanently removes the demo tenants and everything that belongs to them. */
    public function remove(): int
    {
        $tenantIds = Tenant::withTrashed()->whereIn('email', [self::ACTIVE_EMAIL, self::PENDING_EMAIL])->pluck('id');

        DB::transaction(function () use ($tenantIds) {
            $this->context->runWithoutScope(function () use ($tenantIds) {
                foreach ([FleetDocument::class, VehicleExpense::class, VehicleMaintenance::class, SubscriptionPayment::class] as $model) {
                    $model::withoutGlobalScopes()->whereIn('tenant_id', $tenantIds)->delete();
                }
                DB::table('payment_orders')->whereIn('tenant_id', $tenantIds)->delete();
                Driver::withoutGlobalScopes()->withTrashed()->whereIn('tenant_id', $tenantIds)->forceDelete();
                Vehicle::withoutGlobalScopes()->withTrashed()->whereIn('tenant_id', $tenantIds)->forceDelete();
                SupportTicket::withoutGlobalScopes()->whereIn('tenant_id', $tenantIds)->delete();
                DB::table('subscriptions')->whereIn('tenant_id', $tenantIds)->update(['superseded_by' => null]);
                DB::table('subscriptions')->whereIn('tenant_id', $tenantIds)->delete();
                DB::table('tenant_documents')->whereIn('tenant_id', $tenantIds)->delete();
                User::withTrashed()->whereIn('tenant_id', $tenantIds)->update(['branch_id' => null]);
                Branch::withoutGlobalScopes()->withTrashed()->whereIn('tenant_id', $tenantIds)->forceDelete();
                DB::table('tenants')->whereIn('id', $tenantIds)->update(['approved_by' => null]);
                User::withTrashed()->whereIn('tenant_id', $tenantIds)->forceDelete();
                Tenant::withTrashed()->whereIn('id', $tenantIds)->forceDelete();
            });

            $marker = json_decode((string) PlatformSetting::getValue(self::MARKER), true) ?: [];
            ContactMessage::whereIn('id', $marker['enquiries'] ?? [])->delete();
            PlatformSetting::setValue(self::MARKER, null);
        });

        return $tenantIds->count();
    }
}
