<?php

namespace App\Services\Finance;

use App\Models\Booking;
use App\Models\DriverSettlement;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use App\Models\VehicleMaintenance;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/** Operator reports. All queries go through tenant-scoped models. Revenue = fare excl. GST & reimbursements. */
class ReportService
{
    public function __construct(private CarbonInterface $from, private CarbonInterface $to) {}

    public static function for(CarbonInterface $from, CarbonInterface $to): self
    {
        return new self($from->copy()->startOfDay(), $to->copy()->endOfDay());
    }

    /** Completed bookings whose trip finished in the period. */
    public function completedBookings(): Collection
    {
        return Booking::query()->with(['trip.vehicle:id,registration_number', 'trip.driver:id,name', 'category:id,name'])
            ->where('status', 'completed')
            ->whereHas('trip', fn ($q) => $q->whereBetween('completed_at', [$this->from, $this->to]))
            ->get()->sortBy(fn ($b) => $b->trip->completed_at)->values();
    }

    public function summary(): array
    {
        $completed = $this->completedBookings();
        $sum = fn (Collection $c, string $key) => Money::add('0', ...$c->map(fn ($b) => (string) ($b->final_snapshot[$key] ?? 0))->all());

        $payments = Payment::query()->whereBetween('paid_at', [$this->from, $this->to])->get(['amount', 'method', 'kind']);
        $vehicleExp = (string) $this->inRange(VehicleExpense::query(), 'expense_date')->sum('amount');
        $maintenance = (string) $this->inRange(VehicleMaintenance::query(), 'service_date')->sum('cost');
        $general = (string) $this->inRange(Expense::query(), 'expense_date')->sum('amount');
        $payouts = (string) $this->inRange(DriverSettlement::query(), 'paid_on')->sum('amount');

        $revenue = $sum($completed, 'taxable');
        $costs = Money::add($vehicleExp, $maintenance, $general, $payouts);
        $outstanding = Booking::query()->where('status', 'completed')->whereColumn('amount_paid', '<', 'final_total')->get(['final_total', 'amount_paid']);

        return [
            'bookings_created' => Booking::query()->whereBetween('created_at', [$this->from, $this->to])->count(),
            'completed' => $completed->count(),
            'cancelled' => Booking::query()->where('status', 'cancelled')->whereBetween('cancelled_at', [$this->from, $this->to])->count(),
            'revenue' => $revenue,
            'gst' => $sum($completed, 'gst'),
            'reimbursements' => $sum($completed, 'passthrough_total'),
            'billed' => $sum($completed, 'total'),
            'collections' => Money::add('0', ...$payments->where('amount', '>', 0)->pluck('amount')->map(fn ($a) => (string) $a)->all()),
            'refunds' => Money::add('0', ...$payments->where('amount', '<', 0)->pluck('amount')->map(fn ($a) => (string) abs((float) $a))->all()),
            'by_method' => $payments->where('amount', '>', 0)->groupBy('method')->map(fn ($g) => Money::add('0', ...$g->pluck('amount')->map(fn ($a) => (string) $a)->all()))->all(),
            'vehicle_expenses' => number_format((float) $vehicleExp, 2, '.', ''),
            'maintenance' => number_format((float) $maintenance, 2, '.', ''),
            'general_expenses' => number_format((float) $general, 2, '.', ''),
            'driver_payouts' => number_format((float) $payouts, 2, '.', ''),
            'costs' => $costs,
            'profit' => Money::sub($revenue, $costs),
            'outstanding' => Money::add('0', ...$outstanding->map(fn ($b) => Money::sub((string) $b->final_total, (string) $b->amount_paid))->all()),
            'outstanding_count' => $outstanding->count(),
        ];
    }

    /** @return array<string, array{revenue:string, collections:string}> keyed by Y-m-d */
    public function daily(): array
    {
        $days = [];
        for ($d = $this->from->copy(); $d->lte($this->to) && count($days) < 93; $d->addDay()) {
            $days[$d->toDateString()] = ['revenue' => '0.00', 'collections' => '0.00'];
        }
        foreach ($this->completedBookings() as $b) {
            $k = $b->trip->completed_at->toDateString();
            if (isset($days[$k])) {
                $days[$k]['revenue'] = Money::add($days[$k]['revenue'], (string) ($b->final_snapshot['taxable'] ?? 0));
            }
        }
        foreach (Payment::query()->whereBetween('paid_at', [$this->from, $this->to])->where('amount', '>', 0)->get(['amount', 'paid_at']) as $p) {
            $k = $p->paid_at->toDateString();
            if (isset($days[$k])) {
                $days[$k]['collections'] = Money::add($days[$k]['collections'], (string) $p->amount);
            }
        }

        return $days;
    }

    public function vehicles(): Collection
    {
        $exp = $this->inRange(VehicleExpense::query(), 'expense_date')->selectRaw('vehicle_id, sum(amount) as s')->groupBy('vehicle_id')->pluck('s', 'vehicle_id');
        $mnt = $this->inRange(VehicleMaintenance::query(), 'service_date')->selectRaw('vehicle_id, sum(cost) as s')->groupBy('vehicle_id')->pluck('s', 'vehicle_id');
        $trips = Trip::query()->with('booking:id,final_snapshot')->where('status', 'completed')->whereBetween('completed_at', [$this->from, $this->to])->get()->groupBy('vehicle_id');

        return Vehicle::withTrashed()->with('category:id,name')->orderBy('registration_number')->get()->map(function (Vehicle $v) use ($exp, $mnt, $trips) {
            $vt = $trips->get($v->id, collect());
            $revenue = Money::add('0', ...$vt->map(fn ($t) => (string) ($t->booking->final_snapshot['taxable'] ?? 0))->all());
            $km = $vt->sum(fn ($t) => ($t->end_km && $t->start_km) ? $t->end_km - $t->start_km : 0);
            $costs = Money::add((string) ($exp[$v->id] ?? 0), (string) ($mnt[$v->id] ?? 0));

            return (object) ['vehicle' => $v, 'trips' => $vt->count(), 'km' => $km, 'revenue' => $revenue,
                'expenses' => number_format((float) ($exp[$v->id] ?? 0), 2, '.', ''), 'maintenance' => number_format((float) ($mnt[$v->id] ?? 0), 2, '.', ''),
                'profit' => Money::sub($revenue, $costs), 'per_km' => $km > 0 ? round((float) $revenue / $km, 2) : null];
        })->filter(fn ($r) => $r->trips > 0 || (float) $r->expenses > 0 || (float) $r->maintenance > 0)->values();
    }

    /** Date columns are stored as 'Y-m-d 00:00:00' by Eloquent: compare by date, not by string. */
    private function inRange($query, string $column)
    {
        return $query->whereDate($column, '>=', $this->from->toDateString())->whereDate($column, '<=', $this->to->toDateString());
    }

    public function bookings(): Collection
    {
        return $this->completedBookings()->map(function (Booking $b) {
            $s = $b->final_snapshot ?? [];
            $allow = collect($s['lines'] ?? [])->where('code', 'driver_allowance')->sum(fn ($l) => (float) $l['amount']);

            return (object) ['booking' => $b, 'revenue' => (string) ($s['taxable'] ?? 0), 'allowance' => number_format($allow, 2, '.', ''),
                'gst' => (string) ($s['gst'] ?? 0), 'reimbursements' => (string) ($s['passthrough_total'] ?? 0), 'total' => (string) $b->final_total,
                'paid' => (string) $b->amount_paid, 'balance' => $b->balance(), 'km' => ($b->trip->end_km && $b->trip->start_km) ? $b->trip->end_km - $b->trip->start_km : null];
        });
    }

    public function gst(): array
    {
        $docs = $this->inRange(Invoice::query(), 'invoice_date')->orderBy('invoice_date')->orderBy('id')->get();
        $sign = fn (Invoice $i) => $i->isCreditNote() ? -1 : 1;
        $tot = fn (string $f) => number_format($docs->sum(fn ($i) => $sign($i) * (float) $i->{$f}), 2, '.', '');

        return ['documents' => $docs, 'taxable' => $tot('taxable'), 'cgst' => $tot('cgst'), 'sgst' => $tot('sgst'), 'igst' => $tot('igst'), 'reimbursements' => $tot('reimbursements'), 'total' => $tot('total')];
    }
}
