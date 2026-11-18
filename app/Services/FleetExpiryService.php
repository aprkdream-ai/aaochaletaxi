<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\Tenant;
use App\Models\Vehicle;
use App\Notifications\FleetExpiryDigest;
use App\Support\FleetOptions;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/** Daily digest of vehicle/driver documents expiring in 30/15/7/3/1/0 days or expired yesterday. */
class FleetExpiryService
{
    public const STAGES = [30, 15, 7, 3, 1, 0, -1];

    public function __construct(private TenantContext $context) {}

    /** @return list<array{kind:string, name:string, what:string, date:string, days:int, url:string}> */
    public function itemsFor(Tenant $tenant, ?array $stages = null): array
    {
        return $this->context->runAs($tenant, function () use ($stages) {
            $items = [];
            $want = fn (?CarbonInterface $d) => $d && ($days = (int) now()->startOfDay()->diffInDays($d->copy()->startOfDay(), false)) <= 30
                && ($stages === null || in_array($days, $stages, true)) ? $days : null;

            foreach (Vehicle::query()->where('status', '!=', 'inactive')->get() as $v) {
                foreach (FleetOptions::vehicleExpiryFields() as $field => $label) {
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
            usort($items, fn ($a, $b) => $a['days'] <=> $b['days']);

            return $items;
        });
    }

    /** @return int number of tenants notified */
    public function sendDigests(): int
    {
        $sent = 0;
        Tenant::query()->where('status', 'active')->each(function (Tenant $tenant) use (&$sent) {
            $items = $this->itemsFor($tenant, self::STAGES);
            if ($items === [] || ! Cache::add("fleet-expiry:{$tenant->id}:".now()->toDateString(), 1, now()->addDay())) {
                return;
            }
            $tenant->ownerUser()?->notify(new FleetExpiryDigest($items));
            $sent++;
        });

        return $sent;
    }
}
