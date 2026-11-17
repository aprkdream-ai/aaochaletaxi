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
    });
    Route::middleware('permission:admin.tenants.manage')->controller(Admin\TenantController::class)->group(function () {
        Route::post('/operators/{tenant}/approve', 'approve')->name('tenants.approve');
        Route::post('/operators/{tenant}/reject', 'reject')->name('tenants.reject');
        Route::post('/operators/{tenant}/suspend', 'suspend')->name('tenants.suspend');
        Route::post('/operators/{tenant}/reactivate', 'reactivate')->name('tenants.reactivate');
        Route::post('/operators/{tenant}/kyc', 'kyc')->name('tenants.kyc');
    });
});

// ---------- Operator panel ----------
Route::prefix('app')->name('operator.')->middleware(['auth', 'type:operator,vendor', 'tenant'])->group(function () {
    Route::get('/status', Operator\AccountStatusController::class)->name('status');

    Route::middleware('tenant.active')->group(function () {
        Route::get('/', Operator\DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    });
});

// ---------- Driver PWA ----------
Route::prefix('driver')->name('driver.')->middleware(['auth', 'type:driver', 'tenant', 'tenant.active'])->group(function () {
    Route::get('/', Driver\HomeController::class)->name('home');
});

// ---------- Customer account ----------
Route::prefix('account')->name('account.')->middleware(['auth', 'type:customer'])->group(function () {
    Route::get('/', Customer\DashboardController::class)->name('dashboard');
});
