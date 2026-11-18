<?php

namespace Database\Seeders;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\ContactMessage;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\SupportTicketService;
use App\Services\TenantAdminService;
use App\Services\TenantOnboardingService;
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
            $support->reply($ticket, $superAdmin, 'Hi Ramesh! Vehicle management is part of the next release. We will notify you as soon as it is live.');
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
