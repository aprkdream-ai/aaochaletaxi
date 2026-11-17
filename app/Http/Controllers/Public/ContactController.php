<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public const TRIP_TYPES = [
        'outstation_oneway' => 'Outstation one-way',
        'outstation_round' => 'Outstation round trip',
        'local' => 'Local taxi',
        'airport' => 'Airport pickup / drop',
        'railway' => 'Railway station pickup / drop',
        'hourly' => 'Hourly rental',
        'full_day' => 'Full-day rental',
        'corporate' => 'Corporate booking',
    ];

    /** Quick fare-quote request from the home page (stored for the team to call back). */
    public function enquiry(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return back()->with('success', __('Thank you! Our team will call you back shortly.'));
        }

        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $data = $request->validate([
            'trip_type' => ['required', 'in:'.implode(',', array_keys(self::TRIP_TYPES))],
            'pickup' => ['required', 'string', 'max:150'],
            'drop' => ['nullable', 'string', 'max:150'],
            'travel_date' => ['required', 'date', 'after_or_equal:today'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'name' => ['required', 'string', 'max:150'],
        ], ['phone.regex' => 'Enter a valid 10-digit Indian mobile number.']);

        ContactMessage::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'subject' => 'Fare quote: '.self::TRIP_TYPES[$data['trip_type']],
            'message' => sprintf(
                "Trip: %s\nPickup: %s\nDrop: %s\nDate: %s",
                self::TRIP_TYPES[$data['trip_type']], $data['pickup'], $data['drop'] ?: '-', $data['travel_date']
            ),
            'type' => 'enquiry',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', __('Thank you! Our team will call you back shortly.'));
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: bots fill hidden "website" field.
        if (filled($request->input('website'))) {
            return back()->with('success', __('Thank you! We will get back to you shortly.'));
        }

        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['required', 'regex:/^[6-9]\d{9}$/'],
            'subject' => ['nullable', 'string', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
            'type' => ['nullable', 'in:contact,partner'],
        ], ['phone.regex' => 'Enter a valid 10-digit Indian mobile number.']);

        ContactMessage::create([...$data, 'type' => $data['type'] ?? 'contact', 'ip_address' => $request->ip()]);

        return back()->with('success', __('Thank you! We will get back to you shortly.'));
    }
}
