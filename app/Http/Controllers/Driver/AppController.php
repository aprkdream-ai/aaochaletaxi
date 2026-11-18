<?php

namespace App\Http\Controllers\Driver;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\Trip;
use App\Services\Booking\BookingService;
use App\Services\Finance\DriverEarningsService;
use App\Support\FleetOptions;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Mobile-first driver app (PWA). Every action is limited to the logged-in driver's own trips. */
class AppController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    public function home(Request $request): View
    {
        $driver = Driver::query()->where('user_id', $request->user()->id)->first();
        if (! $driver) {
            return view('driver.unlinked');
        }
        $driver->forceFill(['app_seen_at' => now()])->saveQuietly();
        $request->user()->unreadNotifications()->where('data->kind', 'trip_assigned')->update(['read_at' => now()]);

        $base = fn () => Trip::query()->with(['booking.category:id,name', 'vehicle:id,registration_number,make,model'])->where('driver_id', $driver->id);

        return view('driver.home', [
            'driver' => $driver,
            'active' => $base()->where('status', 'started')->first(),
            'today' => $base()->where('status', 'assigned')->whereDate('start_at', today())->orderBy('start_at')->get(),
            'upcoming' => $base()->where('status', 'assigned')->whereDate('start_at', '>', today())->orderBy('start_at')->limit(20)->get(),
            'overdue' => $base()->where('status', 'assigned')->whereDate('start_at', '<', today())->orderBy('start_at')->get(),
            'completedToday' => $base()->where('status', 'completed')->whereDate('completed_at', today())->count(),
        ]);
    }

    public function history(Request $request): View
    {
        $driver = $this->driver($request);

        return view('driver.history', ['trips' => Trip::query()->with('booking:id,booking_number,pickup_address,drop_address,type,final_total')
            ->where('driver_id', $driver->id)->where('status', 'completed')->latest('completed_at')->paginate(20)]);
    }

    public function show(Request $request, Trip $trip): View
    {
        $this->own($request, $trip);
        $trip->load(['booking.stops', 'booking.category', 'vehicle']);

        return view('driver.trip', ['trip' => $trip, 'booking' => $trip->booking, 'mapsUrl' => $this->mapsUrl($trip)]);
    }

    public function start(Request $request, Trip $trip): RedirectResponse
    {
        $this->own($request, $trip);
        $data = $request->validate(['otp' => ['required', 'digits:4'], 'start_km' => ['required', 'integer', 'min:0', 'max:5000000']], ['otp.digits' => __('Enter the 4-digit OTP from the customer.')]);
        if ($trip->start_at->gt(now()->addHours(2))) {
            throw ValidationException::withMessages(['otp' => __('This trip starts at :t. You can start it up to 2 hours early.', ['t' => $trip->start_at->format('d M h:i A')])]);
        }
        $this->bookings->start($trip->booking, $data['otp'], (int) $data['start_km'], $request->user());

        return redirect()->route('driver.trips.show', $trip)->with('success', __('Trip started. Drive safe!'));
    }

    public function complete(Request $request, Trip $trip): RedirectResponse
    {
        $this->own($request, $trip);
        $data = $request->validate([
            'end_km' => ['required', 'integer', 'min:0', 'max:5000000'],
            'waiting_min' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'tolls' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'parking' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'state_tax' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);
        $this->bookings->complete($trip->booking, (int) $data['end_km'], $data, $request->user());

        return redirect()->route('driver.trips.show', $trip)->with('success', __('Trip completed. Collect :amt from the customer (less any advance).', ['amt' => Money::format($trip->booking->fresh()->balance(), 0)]));
    }

    public function availability(Request $request): RedirectResponse
    {
        $driver = $this->driver($request);
        if ($driver->is_available && Trip::query()->where('driver_id', $driver->id)->where('status', 'started')->exists()) {
            return back()->with('error', __('Finish your current trip before going off duty.'));
        }
        $driver->forceFill(['is_available' => ! $driver->is_available])->save();

        return back()->with('success', $driver->is_available ? __('You are on duty.') : __('You are off duty. You will not get new trips.'));
    }

    public function earnings(Request $request, DriverEarningsService $earnings): View
    {
        $driver = $this->driver($request);
        $month = $earnings->compute($driver, now()->startOfMonth(), now());
        $last = $earnings->compute($driver, now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth());

        return view('driver.earnings', ['driver' => $driver, 'month' => $month, 'last' => $last, 'payTypes' => FleetOptions::payTypes(),
            'payouts' => DriverSettlement::query()->where('driver_id', $driver->id)->latest('id')->limit(10)->get()]);
    }

    public function profile(Request $request): View
    {
        $driver = $this->driver($request)->load(['documents', 'branch']);

        return view('driver.profile', ['driver' => $driver, 'docTypes' => FleetOptions::driverDocuments()]);
    }

    /** Polled every ~30 s by the app: unread assignment notifications + active trip state. */
    public function poll(Request $request): JsonResponse
    {
        $driver = $this->driver($request);
        $unread = $request->user()->unreadNotifications()->where('data->kind', 'trip_assigned')->latest()->limit(5)->get();

        return response()->json([
            'new' => $unread->count(),
            'latest' => $unread->first()?->data,
            'on_duty' => $driver->is_available,
            'active_trip' => Trip::query()->where('driver_id', $driver->id)->where('status', 'started')->value('id'),
        ]);
    }

    /** Last known position during a started trip (only the latest point is stored). */
    public function location(Request $request, Trip $trip): JsonResponse
    {
        $this->own($request, $trip);
        abort_unless($trip->status === 'started', 409, 'Trip is not in progress.');
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:6,38'], 'lng' => ['required', 'numeric', 'between:68,98']]); // India bounds
        $trip->forceFill(['last_lat' => $data['lat'], 'last_lng' => $data['lng'], 'last_location_at' => now()])->save();

        return response()->json(['ok' => true]);
    }

    public function pushToken(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);
        $this->driver($request)->forceFill(['fcm_token' => $data['token']])->save();

        return response()->json(['ok' => true]);
    }

    private function driver(Request $request): Driver
    {
        return Driver::query()->where('user_id', $request->user()->id)->firstOrFail();
    }

    private function own(Request $request, Trip $trip): void
    {
        abort_unless($trip->driver_id === $this->driver($request)->id, 404);
        abort_if($trip->status === 'released', 404);
    }

    private function mapsUrl(Trip $trip): string
    {
        $b = $trip->booking;
        $started = $b->status === BookingStatus::Started;
        $dest = $started ? ($b->drop_address ?: $b->pickup_address) : $b->pickup_address;
        $params = ['api' => 1, 'destination' => $dest, 'travelmode' => 'driving'];
        if ($started && $b->stops->isNotEmpty()) {
            $params['waypoints'] = $b->stops->pluck('address')->join('|');
        }

        return 'https://www.google.com/maps/dir/?'.http_build_query($params);
    }
}
