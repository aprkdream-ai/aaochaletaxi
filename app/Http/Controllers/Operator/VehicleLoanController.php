<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use App\Services\VehicleLoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VehicleLoanController extends Controller
{
    public function __construct(private VehicleLoanService $loans) {}

    public function store(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $data = $request->validate([
            'lender' => ['required', 'string', 'max:120'], 'account_number' => ['nullable', 'string', 'max:40'],
            'principal' => ['required', 'numeric', 'min:1', 'max:999999999'], 'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:40'],
            'tenure_months' => ['required', 'integer', 'min:1', 'max:120'], 'emi_amount' => ['nullable', 'numeric', 'min:1', 'max:99999999'],
            'first_emi_date' => ['required', 'date'], 'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['interest_rate'] ??= 0;
        $this->loans->create($data + ['vehicle_id' => $vehicle->id], $request->user());

        return redirect()->route('operator.vehicles.show', [$vehicle, 'tab' => 'loan'])->with('success', __('Loan added.'));
    }

    public function pay(Request $request, VehicleLoan $loan): RedirectResponse
    {
        $data = $request->validate([
            'installment' => ['required', 'integer', 'min:1'], 'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'], 'payment_method' => ['nullable', 'in:cash,upi,card,bank_transfer,other'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);
        $this->loans->pay($loan, (int) $data['installment'], (string) $data['amount'], $data['paid_on'], $data['payment_method'] ?? null, $data['reference'] ?? null, $request->user());

        return back()->with('success', __('EMI :n recorded.', ['n' => $data['installment']]));
    }

    public function close(Request $request, VehicleLoan $loan): RedirectResponse
    {
        $data = $request->validate(['closed_on' => ['required', 'date', 'before_or_equal:today']]);
        $this->loans->close($loan, $data['closed_on']);

        return back()->with('success', __('Loan closed.'));
    }
}
