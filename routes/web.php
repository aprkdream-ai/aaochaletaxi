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
