<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $bookings = Booking::withoutGlobalScopes()->with(['tenant:id,name,slug', 'category' => fn ($q) => $q->select('id', 'name')])
            ->where(fn ($q) => $q->where('customer_user_id', $user->id)->when($user->phone, fn ($w) => $w->orWhere(fn ($x) => $x->where('customer_phone', $user->phone)->where('phone_verified', true))))
            ->orderByDesc('pickup_at')->limit(50)->get();

        return view('customer.dashboard', ['user' => $user, 'bookings' => $bookings]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);
        $user->fill(['name' => $data['name'], 'email' => $data['email'] ? strtolower($data['email']) : null]);
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();

        return back()->with('success', __('Profile updated.'));
    }
}
