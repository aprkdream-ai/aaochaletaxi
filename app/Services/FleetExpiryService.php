<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Tenant;
use App\Models\FleetPart;
use App\Models\Vehicle;
use App\Models\VehicleLoan;
use App\Support\Money;
use App\Models\VehicleMaintenance;
use App\Notifications\FleetExpiryDigest;
use App\Support\FleetOptions;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Daily digest: vehicle/driver documents expiring in 30/15/7/3/1/0 days or expired yesterday,
 * plus maintenance due (next service date in 7/3/1/0 days or yesterday; next service km within 500 km / reached).
 */
class FleetExpiryService
{
    public const STAGES = [30, 15, 7, 3, 1, 0, -1];

    public const SERVICE_STAGES = [7, 3, 1, 0, -1];

    /** Show a km-based service reminder this many km before it is due. */
    public const SERVICE_KM_WARNING = 500;

    /** Date-based service reminders appear on the dashboard this many days ahead. */
    public const SERVICE_DAYS_AHEAD = 15;

    public function __construct(private TenantContext $context) {}

    /** @return list<array{kind:string, name:string, what:string, date:string, days:int, url:string}> */
    public function itemsFor(Tenant $tenant, ?array $stages = null): array
    {
        return $this->context->runAs($tenant, function () use ($stages) {
            $items = [];
            $want = fn (?CarbonInterface $d) => $d && ($days = (int) now()->startOfDay()->diffInDays($d->copy()->startOfDay(), false)) <= 30
                && ($stages === null || in_array($days, $stages, true)) ? $days : null;

            foreach (Vehicle::query()->where('status', '!=', 'inactive')->get() as $v) {
                foreach (FleetOptions::allVehicleExpiryFields() as $field => $label) {
                    if (($days = $want($v->{$field})) !== null) {
                        $items[] = ['kind' => 'vehicle', 'name' => $v->displayRegistration(), 'what' => $label, 'date' => $v->{$field}->format('d M Y'), 'days' => $days, 'url' => route('operator.vehicles.show', $v)];
                    }
                }
            }
            foreach (Driver::query()->where('status', '!=', 'inactive')->get() as $d) {
                if (($days = $want($d->licence_expiry)) !== null) {
                    $items[] = ['kind' => 'driver', 'name' => $d->name, 'what' => __('Driving licence'), 'date' => $d->licence_expiry->format('d M Y'), 'days' => $days, 'url' => route('operator.drivers.show', $d)];
                }
            }
            // Next unpaid EMI of each active loan: 7 days ahead, or overdue.
            foreach (VehicleLoan::query()->with(['vehicle:id,registration_number', 'payments:id,loan_id,installment_no'])->where('status', 'active')->get() as $loan) {
                $paid = $loan->payments->pluck('installment_no')->all();
                $next = collect(range(1, $loan->tenure_months))->first(fn ($n) => ! in_array($n, $paid, true));
                if ($next === null) {
                    continue;
                }
                $due = $loan->dueDate($next);
                $days = (int) now()->startOfDay()->diffInDays($due->copy()->startOfDay(), false);
                if ($days <= 7 && ($stages === null || in_array($days, $stages, true))) {
                    $items[] = ['kind' => 'emi', 'name' => $loan->vehicle?->displayRegistration() ?? '—', 'what' => __('EMI :n · :amt · :lender', ['n' => $next, 'amt' => Money::format($loan->emi_amount, 0), 'lender' => $loan->lender]),
                        'date' => $due->format('d M Y'), 'days' => $days, 'url' => route('operator.vehicles.show', [$loan->vehicle_id, 'tab' => 'loan'])];
                }
            }
            foreach (FleetPart::query()->where('status', '!=', 'scrapped')->whereNotNull('warranty_until')->where('warranty_until', '<=', now()->addDays(30)->toDateString())
                ->where('warranty_until', '>=', now()->subDay()->toDateString())->get() as $part) {
                if (($days = $want($part->warranty_until)) !== null) {
                    $items[] = ['kind' => 'part', 'name' => $part->label(), 'what' => __(':type warranty', ['type' => FleetOptions::partTypes()[$part->type] ?? $part->type]),
                        'date' => $part->warranty_until->format('d M Y'), 'days' => $days, 'url' => route('operator.parts.index', ['q' => $part->serial_number ?: $part->brand])];
                }
            }
            usort($items, fn ($a, $b) => $a['days'] <=> $b['days']);

            return $items;
        });
    }

    /**
     * Maintenance reminders from the latest record of each maintenance type per vehicle that set a next date and/or km.
     * A newer record of the same type replaces the reminder (service done).
     *
     * @param  bool  $digest  true = only items to notify today (date stages; km reminders once per stage)
     * @return list<array{kind:string, name:string, what:string, date:string, days:?int, km_left:?int, url:string}>
     */
    public function serviceItemsFor(Tenant $tenant, bool $digest = false): array
    {
        return $this->context->runAs($tenant, function () use ($digest) {
            $vehicles = Vehicle::query()->where('status', '!=', 'inactive')->get(['id', 'registration_number', 'odometer_km'])->keyBy('id');
            $latest = VehicleMaintenance::query()->whereIn('vehicle_id', $vehicles->keys())->where('service_date', '>=', now()->subYears(3)->toDateString())
                ->orderBy('service_date')->orderBy('id')
                ->get(['id', 'vehicle_id', 'type', 'service_date', 'next_service_date', 'next_service_km'])
                ->keyBy(fn ($m) => $m->vehicle_id.'|'.$m->type); // last one per vehicle+type wins
            $types = FleetOptions::maintenanceTypes();
            $items = [];

            foreach ($latest as $m) {
                $v = $vehicles[$m->vehicle_id];
                $days = $m->next_service_date ? (int) now()->startOfDay()->diffInDays($m->next_service_date->copy()->startOfDay(), false) : null;
                $kmLeft = ($m->next_service_km && $v->odometer_km !== null) ? (int) $m->next_service_km - (int) $v->odometer_km : null;

                $dateHit = $days !== null && ($digest ? in_array($days, self::SERVICE_STAGES, true) : $days <= self::SERVICE_DAYS_AHEAD);
                $kmHit = $kmLeft !== null && $kmLeft <= self::SERVICE_KM_WARNING
                    && (! $digest || Cache::add("service-km:{$m->id}:".($kmLeft <= 0 ? 'due' : 'soon'), 1, now()->addDays(120)));
                if (! $dateHit && ! $kmHit) {
                    continue;
                }

                $when = collect([
                    $m->next_service_date?->format('d M Y'),
                    $m->next_service_km ? number_format($m->next_service_km).' km' : null,
                ])->filter()->join(' / ');
                $items[] = ['kind' => 'service', 'name' => $v->displayRegistration(), 'what' => __(':type due', ['type' => $types[$m->type] ?? $m->type]),
                    'date' => $when, 'days' => $days, 'km_left' => $kmLeft, 'url' => route('operator.vehicles.show', [$v->id, 'tab' => 'maintenance'])];
            }
            usort($items, fn ($a, $b) => self::urgency($a) <=> self::urgency($b));

            return $items;
        });
    }

    /** Lower = more urgent; overdue by date or km first. */
    public static function urgency(array $item): int
    {
        $byKm = isset($item['km_left']) ? intdiv((int) $item['km_left'], 50) : PHP_INT_MAX;

        return min($item['days'] ?? PHP_INT_MAX, $byKm);
    }

    /** @return int number of tenants notified */
    public function sendDigests(): int
    {
        $sent = 0;
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use (&$sent) {
            $key = "fleet-expiry:{$tenant->id}:".now()->toDateString();
            if (Cache::has($key)) {
                return;
            }
            $items = array_merge($this->itemsFor($tenant, self::STAGES), $this->serviceItemsFor($tenant, digest: true));
            if ($items === [] || ! Cache::add($key, 1, now()->addDay())) {
                return;
            }
            $tenant->ownerUser()?->notify(new FleetExpiryDigest($items));
            $sent++;
        });

        return $sent;
    }
}
