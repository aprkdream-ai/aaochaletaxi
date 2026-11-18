<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\Phone;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(TenantContext $context): View
    {
        return view('operator.profile', ['tenant' => $context->get()]);
    }

    public function update(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $tenant = $context->get();
        $request->merge([
            'phone' => Phone::normalize($request->input('phone')),
            'gstin' => $request->filled('gstin') ? strtoupper(trim($request->input('gstin'))) : null,
            'pan' => $request->filled('pan') ? strtoupper(trim($request->input('pan'))) : null,
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'pan' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'digits:6'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['nullable', 'boolean'],
            'booking_page_enabled' => ['nullable', 'boolean'],
            'notify_customers' => ['nullable', 'boolean'],
            'online_advance_percent' => ['nullable', 'integer', 'in:0,10,20,25,30,50,100'],
            'about' => ['nullable', 'string', 'max:600'],
        ], [
            'phone.regex' => __('Enter a valid 10-digit Indian mobile number.'),
            'gstin.regex' => __('Enter a valid 15-character GSTIN.'),
            'pan.regex' => __('Enter a valid PAN (e.g. ABCDE1234F).'),
        ]);

        $old = $tenant->only(['name', 'legal_name', 'email', 'phone', 'gstin', 'pan', 'city', 'state']);
        $settingKeys = ['booking_page_enabled', 'notify_customers', 'online_advance_percent', 'about'];
        $tenant->fill(collect($data)->except(array_merge(['logo', 'remove_logo'], $settingKeys))->all());
        $tenant->settings = array_merge($tenant->settings ?? [], [
            'booking_page_enabled' => $request->boolean('booking_page_enabled'),
            'notify_customers' => $request->boolean('notify_customers'),
            'online_advance_percent' => (int) ($data['online_advance_percent'] ?? 0),
            'about' => $data['about'] ?? null,
        ]);

        if ($request->hasFile('logo')) {
            $previous = $tenant->logo_path;
            $tenant->logo_path = $request->file('logo')->storeAs('logos/'.$tenant->uuid, Str::random(24).'.'.$request->file('logo')->guessExtension(), 'public');
            if ($previous) {
                Storage::disk('public')->delete($previous);
            }
        } elseif ($request->boolean('remove_logo') && $tenant->logo_path) {
            Storage::disk('public')->delete($tenant->logo_path);
            $tenant->logo_path = null;
        }

        $tenant->save();
        $audit->log('tenant.profile_updated', $tenant, $old, $tenant->only(array_keys($old)));

        return back()->with('success', __('Business profile saved.'));
    }
}
