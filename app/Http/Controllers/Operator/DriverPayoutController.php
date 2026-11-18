<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Services\Finance\DriverEarningsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverPayoutController extends Controller
{
    public function __construct(private DriverEarningsService $earnings) {}

    public function index(Request $request): View
    {
        $range = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        [$from, $to] = DriverEarningsService::parseRange($range['from'] ?? null, $range['to'] ?? null);

        return view('operator.finance.payouts', [
            'from' => $from, 'to' => $to,
            'rows' => Driver::query()->orderBy('name')->get()->map(fn (Driver $d) => (object) (['driver' => $d] + $this->earnings->compute($d, $from, $to))),
            'recent' => DriverSettlement::query()->with(['driver:id,name', 'recorder:id,name'])->latest('id')->limit(15)->get(),
        ]);
    }

    public function show(Request $request, Driver $driver): View
    {
        $range = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        [$from, $to] = DriverEarningsService::parseRange($range['from'] ?? null, $range['to'] ?? null);

        return view('operator.finance.payout-driver', [
            'driver' => $driver, 'from' => $from, 'to' => $to, 'calc' => $this->earnings->compute($driver, $from, $to),
            'history' => DriverSettlement::query()->with('recorder:id,name')->where('driver_id', $driver->id)->latest('id')->get(),
        ]);
    }

    public function store(Request $request, Driver $driver): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'], 'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'], 'deductions' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'method' => ['required', 'in:cash,upi,bank_transfer,cheque,other'], 'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        [$from, $to] = DriverEarningsService::parseRange($data['from'], $data['to']);
        $this->earnings->settle($driver, $from, $to, $data['amount'], $data['deductions'] ?? 0, $data['method'], $data['reference'] ?? null, $data['notes'] ?? null, $request->user());

        return back()->with('success', __('Payout of ₹:a recorded for :name.', ['a' => number_format((float) $data['amount'], 2), 'name' => $driver->name]));
    }
}
