<?php

namespace App\Http\Controllers\Operator;

use App\Enums\BookingType;
use App\Http\Controllers\Controller;
use App\Models\FareRule;
use App\Models\VehicleCategory;
use App\Services\AuditLogger;
use App\Services\Booking\FareRuleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FareRuleController extends Controller
{
    public function index(Request $request, TenantContext $context, FareRuleService $service): View
    {
        if (FareRule::query()->doesntExist()) {
            $service->seedDefaults($context->get()); // first visit: start from suggested rates
        }
        $type = BookingType::tryFrom((string) $request->query('type')) ?? BookingType::OutstationOneway;

        return view('operator.fares.index', [
            'type' => $type,
            'categories' => VehicleCategory::query()->availableTo($context->id())->get(),
            'rules' => FareRule::query()->where('booking_type', $type->value)->get()->keyBy('vehicle_category_id'),
        ]);
    }

    public function edit(FareRule $fareRule): View
    {
        return view('operator.fares.edit', ['rule' => $fareRule->load('category')]);
    }

    public function update(Request $request, FareRule $fareRule, AuditLogger $audit): RedirectResponse
    {
        $money = ['nullable', 'numeric', 'min:0', 'max:999999'];
        $data = $request->validate([
            'base_fare' => $money, 'per_km' => $money, 'extra_km_rate' => $money, 'extra_hour_rate' => $money,
            'driver_allowance' => $money, 'night_charge' => $money, 'waiting_per_min' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'min_km' => ['nullable', 'integer', 'min:0', 'max:2000'], 'included_km' => ['nullable', 'integer', 'min:0', 'max:2000'],
            'included_hours' => ['nullable', 'integer', 'min:0', 'max:24'], 'waiting_free_min' => ['nullable', 'integer', 'min:0', 'max:600'],
            'night_start' => ['required', 'date_format:H:i'], 'night_end' => ['required', 'date_format:H:i'],
            'gst_percent' => ['required', 'numeric', 'in:0,5,12,18'],
            'is_active' => ['nullable', 'boolean'],
        ], ['gst_percent.in' => __('GST on passenger transport is 0%, 5%, 12% or 18%.')]);

        $data = array_map(fn ($v) => $v ?? 0, $data);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $old = $fareRule->only(array_keys($data));
        $fareRule->update($data);
        $audit->log('fare_rule.updated', $fareRule, $old, $data);

        return redirect()->route('operator.fares.index', ['type' => $fareRule->booking_type->value])->with('success', __('Fare rule saved. Existing quotations are not affected.'));
    }
}
