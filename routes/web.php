<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\CustomerRegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OperatorRegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Customer;
use App\Http\Controllers\Driver;
use App\Http\Controllers\Operator;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\LocaleController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\Webhooks\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

// ---------- Public website ----------
Route::controller(PageController::class)->group(function () {
    Route::get('/', 'home')->name('home');
    Route::get('/about', 'about')->name('about');
    Route::get('/services', 'services')->name('services');
    Route::get('/become-a-partner', 'partner')->name('partner');
    Route::get('/contact', 'contact')->name('contact');
    Route::get('/privacy-policy', 'legal')->defaults('page', 'privacy')->name('legal.privacy');
    Route::get('/terms-and-conditions', 'legal')->defaults('page', 'terms')->name('legal.terms');
    Route::get('/cancellation-refund-policy', 'legal')->defaults('page', 'refund')->name('legal.refund');
});
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:forms')->name('contact.store');
Route::post('/enquiry', [ContactController::class, 'enquiry'])->middleware('throttle:forms')->name('enquiry.store');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/locale/{locale}', LocaleController::class)->name('locale');
Route::post('/webhooks/razorpay', RazorpayWebhookController::class)->middleware('throttle:120,1')->name('webhooks.razorpay');

// ---------- Authentication ----------
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');

    Route::get('/register/operator', [OperatorRegisterController::class, 'create'])->name('register.operator');
    Route::post('/register/operator', [OperatorRegisterController::class, 'store'])->middleware('throttle:register')->name('register.operator.store');
    Route::get('/register', [CustomerRegisterController::class, 'create'])->name('register.customer');
    Route::post('/register', [CustomerRegisterController::class, 'store'])->middleware('throttle:register')->name('register.customer.store');

    Route::get('/forgot-password', [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:forms')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:forms')->name('password.update');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// ---------- Super Admin / platform staff ----------
Route::prefix('admin')->name('admin.')->middleware(['auth', 'type:super_admin,platform_staff', 'platform'])->group(function () {
    Route::get('/', Admin\DashboardController::class)->middleware('permission:admin.dashboard.view')->name('dashboard');

    Route::middleware('permission:admin.tenants.view')->group(function () {
        Route::get('/operators', [Admin\TenantController::class, 'index'])->name('tenants.index');
        Route::get('/operators/{tenant}', [Admin\TenantController::class, 'show'])->name('tenants.show');
        Route::get('/kyc/{document}/file', [Admin\KycController::class, 'download'])->name('kyc.download');
    });
    Route::middleware('permission:admin.tenants.manage')->group(function () {
        Route::controller(Admin\TenantController::class)->group(function () {
            Route::post('/operators/{tenant}/approve', 'approve')->name('tenants.approve');
            Route::post('/operators/{tenant}/reject', 'reject')->name('tenants.reject');
            Route::post('/operators/{tenant}/suspend', 'suspend')->name('tenants.suspend');
            Route::post('/operators/{tenant}/reactivate', 'reactivate')->name('tenants.reactivate');
            Route::post('/operators/{tenant}/kyc', 'kyc')->name('tenants.kyc');
        });
        Route::post('/kyc/{document}/review', [Admin\KycController::class, 'review'])->name('kyc.review');
    });

    Route::middleware('permission:admin.subscriptions.manage')->controller(Admin\SubscriptionController::class)->group(function () {
        Route::get('/subscriptions', 'index')->name('subscriptions.index');
        Route::post('/operators/{tenant}/subscriptions', 'activate')->name('subscriptions.activate');
        Route::post('/subscriptions/{subscription}/change-plan', 'changePlan')->name('subscriptions.change-plan');
        Route::post('/subscriptions/{subscription}/extend', 'extend')->name('subscriptions.extend');
        Route::post('/subscriptions/{subscription}/cancel', 'cancel')->name('subscriptions.cancel');
        Route::post('/subscriptions/{subscription}/payments', 'recordPayment')->name('subscriptions.payments.store');
        Route::get('/invoices/{payment}', 'invoice')->name('invoices.show');
    });

    Route::middleware('permission:admin.plans.manage')->group(function () {
        Route::resource('plans', Admin\PlanController::class)->except(['show', 'destroy']);
    });

    Route::middleware('permission:admin.support.manage')->group(function () {
        Route::get('/enquiries', [Admin\EnquiryController::class, 'index'])->name('enquiries.index');
        Route::patch('/enquiries/{enquiry}', [Admin\EnquiryController::class, 'update'])->name('enquiries.update');
        Route::get('/support', [Admin\SupportTicketController::class, 'index'])->name('support.index');
        Route::get('/support/{ticket}', [Admin\SupportTicketController::class, 'show'])->name('support.show');
        Route::post('/support/{ticket}/reply', [Admin\SupportTicketController::class, 'reply'])->name('support.reply');
        Route::patch('/support/{ticket}', [Admin\SupportTicketController::class, 'update'])->name('support.update');
    });

    Route::middleware('permission:admin.users.manage')->group(function () {
        Route::get('/staff', [Admin\StaffController::class, 'index'])->name('staff.index');
        Route::get('/staff/create', [Admin\StaffController::class, 'create'])->name('staff.create');
        Route::post('/staff', [Admin\StaffController::class, 'store'])->name('staff.store');
        Route::get('/staff/{member}/edit', [Admin\StaffController::class, 'edit'])->name('staff.edit');
        Route::put('/staff/{member}', [Admin\StaffController::class, 'update'])->name('staff.update');
    });

    Route::middleware('permission:admin.settings.manage')->group(function () {
        Route::get('/settings', [Admin\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('/settings', [Admin\SettingsController::class, 'update'])->name('settings.update');
        Route::post('/settings/test/{integration}', [Admin\SettingsController::class, 'test'])->middleware('throttle:forms')->name('settings.test');
        Route::get('/message-log', [Admin\MessageLogController::class, 'index'])->name('messages.index');
    });
    Route::get('/audit-log', [Admin\AuditLogController::class, 'index'])->middleware('permission:admin.audit.view')->name('audit.index');
});

// ---------- Operator panel ----------
Route::prefix('app')->name('operator.')->middleware(['auth', 'type:operator,vendor', 'tenant'])->group(function () {
    Route::get('/status', Operator\AccountStatusController::class)->name('status');

    // Available even while pending/suspended/expired: profile, KYC, subscription, support.
    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/profile', [Operator\ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [Operator\ProfileController::class, 'update'])->name('profile.update');
        Route::get('/kyc', [Operator\KycController::class, 'index'])->name('kyc.index');
        Route::post('/kyc', [Operator\KycController::class, 'store'])->middleware('throttle:forms')->name('kyc.store');
        Route::delete('/kyc/{document}', [Operator\KycController::class, 'destroy'])->name('kyc.destroy');
        Route::get('/kyc/{document}/file', [Operator\KycController::class, 'download'])->name('kyc.download');
    });
    Route::middleware('permission:subscription.manage')->group(function () {
        Route::get('/subscription', [Operator\SubscriptionController::class, 'show'])->name('subscription');
        Route::post('/subscription/checkout', [Operator\CheckoutController::class, 'store'])->middleware('throttle:forms')->name('checkout.store');
        Route::post('/subscription/checkout/verify', [Operator\CheckoutController::class, 'verify'])->name('checkout.verify');
        Route::get('/invoices/{payment}', [Operator\SubscriptionController::class, 'invoice'])->name('invoices.show');
    });
    Route::controller(Operator\SupportTicketController::class)->group(function () {
        Route::get('/support', 'index')->name('support.index');
        Route::get('/support/new', 'create')->name('support.create');
        Route::post('/support', 'store')->middleware('throttle:forms')->name('support.store');
        Route::get('/support/{ticket}', 'show')->name('support.show');
        Route::post('/support/{ticket}/reply', 'reply')->middleware('throttle:forms')->name('support.reply');
        Route::post('/support/{ticket}/close', 'close')->name('support.close');
    });

    Route::middleware(['tenant.active', 'subscription'])->group(function () {
        Route::get('/', Operator\DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');

        // Organisation
        Route::resource('branches', Operator\BranchController::class)->except('show')->middleware('permission:branches.manage');
        Route::middleware('permission:staff.manage')->group(function () {
            Route::get('/staff', [Operator\StaffController::class, 'index'])->name('staff.index');
            Route::get('/staff/create', [Operator\StaffController::class, 'create'])->name('staff.create');
            Route::post('/staff', [Operator\StaffController::class, 'store'])->name('staff.store');
            Route::get('/staff/{member}/edit', [Operator\StaffController::class, 'edit'])->name('staff.edit');
            Route::put('/staff/{member}', [Operator\StaffController::class, 'update'])->name('staff.update');
        });
        Route::resource('roles', Operator\RoleController::class)->except('show')->middleware('permission:roles.manage');

        // Vehicles
        Route::middleware('permission:vehicles.manage')->group(function () {
            Route::get('/vehicles/create', [Operator\VehicleController::class, 'create'])->name('vehicles.create');
            Route::post('/vehicles', [Operator\VehicleController::class, 'store'])->name('vehicles.store');
            Route::get('/vehicles/{vehicle}/edit', [Operator\VehicleController::class, 'edit'])->name('vehicles.edit');
            Route::put('/vehicles/{vehicle}', [Operator\VehicleController::class, 'update'])->name('vehicles.update');
            Route::delete('/vehicles/{vehicle}', [Operator\VehicleController::class, 'destroy'])->name('vehicles.destroy');
            Route::post('/vehicles/{vehicle}/availability', [Operator\VehicleController::class, 'toggleAvailability'])->name('vehicles.availability');
            Route::post('/vehicles/{vehicle}/documents', [Operator\FleetRecordController::class, 'storeVehicleDocument'])->name('vehicles.documents.store');
            Route::post('/vehicles/{vehicle}/maintenance', [Operator\FleetRecordController::class, 'storeMaintenance'])->name('vehicles.maintenance.store');
            Route::delete('/maintenance/{maintenance}', [Operator\FleetRecordController::class, 'destroyMaintenance'])->name('maintenance.destroy');
            Route::post('/vehicle-categories', [Operator\VehicleCategoryController::class, 'store'])->name('vehicle-categories.store');
        });
        Route::middleware('permission:expenses.manage,vehicles.manage')->group(function () {
            Route::post('/vehicles/{vehicle}/expenses', [Operator\FleetRecordController::class, 'storeExpense'])->name('vehicles.expenses.store');
            Route::delete('/expenses/{expense}', [Operator\FleetRecordController::class, 'destroyExpense'])->name('expenses.destroy');
        });
        Route::middleware('permission:vehicles.view,vehicles.manage')->group(function () {
            Route::get('/vehicles', [Operator\VehicleController::class, 'index'])->name('vehicles.index');
            Route::get('/vehicles/{vehicle}', [Operator\VehicleController::class, 'show'])->name('vehicles.show');
        });

        // Drivers
        Route::middleware('permission:drivers.manage')->group(function () {
            Route::get('/drivers/create', [Operator\DriverController::class, 'create'])->name('drivers.create');
            Route::post('/drivers', [Operator\DriverController::class, 'store'])->name('drivers.store');
            Route::get('/drivers/{driver}/edit', [Operator\DriverController::class, 'edit'])->name('drivers.edit');
            Route::put('/drivers/{driver}', [Operator\DriverController::class, 'update'])->name('drivers.update');
            Route::delete('/drivers/{driver}', [Operator\DriverController::class, 'destroy'])->name('drivers.destroy');
            Route::post('/drivers/{driver}/app-access', [Operator\DriverController::class, 'appAccess'])->name('drivers.app-access');
            Route::post('/drivers/{driver}/availability', [Operator\DriverController::class, 'toggleAvailability'])->name('drivers.availability');
            Route::post('/drivers/{driver}/documents', [Operator\FleetRecordController::class, 'storeDriverDocument'])->name('drivers.documents.store');
        });
        Route::middleware('permission:drivers.view,drivers.manage')->group(function () {
            Route::get('/drivers', [Operator\DriverController::class, 'index'])->name('drivers.index');
            Route::get('/drivers/{driver}', [Operator\DriverController::class, 'show'])->name('drivers.show');
        });

        // Documents (permission checked per owner type in controller)
        Route::get('/fleet-documents/{document}', [Operator\FleetRecordController::class, 'downloadDocument'])->name('fleet-documents.show');
        Route::delete('/fleet-documents/{document}', [Operator\FleetRecordController::class, 'destroyDocument'])->name('fleet-documents.destroy');
    });
});

// ---------- Driver PWA ----------
Route::prefix('driver')->name('driver.')->middleware(['auth', 'type:driver', 'tenant', 'tenant.active', 'subscription'])->group(function () {
    Route::get('/', Driver\HomeController::class)->name('home');
});

// ---------- Customer account ----------
Route::prefix('account')->name('account.')->middleware(['auth', 'type:customer'])->group(function () {
    Route::get('/', Customer\DashboardController::class)->name('dashboard');
});
