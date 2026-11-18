<?php

namespace Database\Seeders;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Branch;
use App\Models\ContactMessage;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleCategory;
use App\Models\VehicleExpense;
use App\Services\DriverService;
use App\Services\SubscriptionService;
use App\Services\SupportTicketService;
use App\Services\TenantAdminService;
use App\Services\TenantOnboardingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

/** Local/demo data only. Never runs in production. */
class DemoSeeder extends Seeder
{
    public function run(TenantOnboardingService $onboarding, TenantAdminService $admin, SubscriptionService $subscriptions, SupportTicketService $support): void
    {
        if (app()->isProduction()) {
            return;
        }

        $superAdmin = User::query()->where('type', UserType::SuperAdmin)->first();
        if (! $superAdmin) {
            $superAdmin = new User(['name' => 'Super Admin', 'email' => 'admin@aaochaletaxi.test', 'password' => 'password']);
            $superAdmin->type = UserType::SuperAdmin;
            $superAdmin->status = UserStatus::Active;
            $superAdmin->save();
        }

        if (! Tenant::query()->where('email', 'owner@shivtravels.test')->exists()) {
            $owner = $onboarding->register(['business_name' => 'Shiv Travels', 'city' => 'Ahmedabad', 'state' => 'Gujarat', 'name' => 'Ramesh Patel', 'email' => 'owner@shivtravels.test', 'phone' => '9876500001', 'password' => 'password1']);
            $tenant = $owner->tenant;
            $admin->approve($tenant, $superAdmin);

            // Paid Growth plan with a GST invoice.
            $subscriptions->activate($tenant, SubscriptionPlan::where('code', 'growth')->firstOrFail(), 'monthly', 'now', $superAdmin,
                ['amount' => 1499, 'method' => 'upi', 'reference' => 'UPI-DEMO-1001']);

            // A support conversation.
            $ticket = $support->open($tenant, $owner, 'How do I add my Innova Crysta?', TicketCategory::Technical, TicketPriority::Normal, 'I want to add a 7-seater to my fleet. Where do I do that?');
            $support->reply($ticket, $superAdmin, 'Hi Ramesh! Go to Fleet → Vehicles → Add vehicle. Choose the MUV category for the Crysta.');

            // Fleet demo data.
            app(TenantContext::class)->runAs($tenant, function () use ($owner) {
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
                $drivers = app(DriverService::class);
                $drivers->create(Tenant::find($owner->tenant_id), ['name' => 'Suresh Yadav', 'phone' => '9811122233', 'licence_number' => 'GJ0120190012345', 'licence_type' => 'LMV-TR', 'licence_expiry' => now()->addDays(20), 'pay_type' => 'monthly', 'pay_amount' => 18000, 'status' => 'active', 'is_available' => true, 'branch_id' => $branch->id, 'joined_on' => now()->subYear()], 'driver123');
                $drivers->create(Tenant::find($owner->tenant_id), ['name' => 'Mahesh Thakor', 'phone' => '9811122244', 'licence_number' => 'GJ0120180098765', 'licence_type' => 'LMV-TR', 'licence_expiry' => now()->addYears(4), 'pay_type' => 'per_trip', 'pay_amount' => 600, 'status' => 'active', 'is_available' => true, 'branch_id' => $branch->id], null);
            });
        }

        if (ContactMessage::query()->doesntExist()) {
            foreach ([
                ['Priya Shah', '9825012345', 'enquiry', 'Fare quote: Outstation one-way', "Trip: Outstation one-way\nPickup: Ahmedabad\nDrop: Udaipur\nDate: ".now()->addDays(3)->toDateString()],
                ['Arjun Mehta', '9898012345', 'enquiry', 'Fare quote: Airport pickup / drop', "Trip: Airport pickup / drop\nPickup: SVPI Airport\nDrop: Gandhinagar\nDate: ".now()->addDay()->toDateString()],
                ['Kiran Desai', '9712012345', 'partner', 'Fleet of 12 cars', 'We run 12 cars in Vadodara. Please share a demo.'],
            ] as [$name, $phone, $type, $subject, $message]) {
                ContactMessage::create(compact('name', 'phone', 'type', 'subject', 'message'));
            }
        }

        if (! Tenant::query()->where('email', 'owner@ganeshcabs.test')->exists()) {
            $onboarding->register(['business_name' => 'Ganesh Cabs', 'city' => 'Pune', 'state' => 'Maharashtra', 'name' => 'Sunil Joshi', 'email' => 'owner@ganeshcabs.test', 'phone' => '9876500002', 'password' => 'password1']);
        }
    }
}
