<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\Finance\FleetReportService;
use App\Support\Modules;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Vehicle running cost & utilisation, and department allocation (Fleet module). Available on every plan. */
class FleetReportController extends Controller
{
    public function index(Request $request, FleetReportService $report, TenantContext $context): View
    {
        [$from, $to] = $this->period($request);
        $vehicles = $report->vehicles($from, $to);
        $fleet = $context->get()->hasModule(Modules::FLEET);

        return view('operator.fleet-report.index', [
            'from' => $from, 'to' => $to, 'vehicles' => $vehicles, 'fleet' => $fleet,
            'departments' => $fleet ? $report->departments($from, $to, $vehicles) : collect(),
            'totals' => (object) [
                'km' => $vehicles->sum('km'), 'trips' => $vehicles->sum('trips'),
                'cost' => \App\Support\Money::add('0', ...$vehicles->pluck('total')->all()),
            ],
        ]);
    }

    public function export(Request $request, FleetReportService $report, TenantContext $context): StreamedResponse
    {
        [$from, $to] = $this->period($request);
        $vehicles = $report->vehicles($from, $to);
        $tab = $request->query('tab') === 'departments' && $context->get()->hasModule(Modules::FLEET) ? 'departments' : 'vehicles';

        [$head, $rows] = $tab === 'departments'
            ? [['Department', 'Trips', 'Km', 'Hours', 'Allocated cost'], $report->departments($from, $to, $vehicles)->map(fn ($d) => [$d->department, $d->trips, $d->km, $d->hours, $d->cost])]
            : [['Vehicle', 'Type', 'Trips', 'Fleet trips', 'Km', 'Hours', 'Fuel (L/kWh)', 'Avg km/l', 'Fuel cost', 'Other expenses', 'Maintenance', 'Total cost', 'Cost per km'],
                $vehicles->map(fn ($r) => [$r->vehicle->registration_number, $r->vehicle->vehicle_type, $r->trips, $r->fleet_trips, $r->km, $r->hours, $r->litres, $r->kmpl, $r->fuel, $r->other, $r->maintenance, $r->total, $r->per_km])];

        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $head);
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) && ! is_numeric($v) ? "'".$v : $v, $row)); // CSV injection guard
            }
            fclose($out);
        }, sprintf('fleet-%s-%s-to-%s.csv', $tab, $from->toDateString(), $to->toDateString()), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function period(Request $request): array
    {
        $d = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = isset($d['from']) ? Carbon::parse($d['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($d['to']) ? Carbon::parse($d['to'])->endOfDay() : now()->endOfDay();
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subYear()->startOfDay();
        }

        return [$from, $to];
    }
}
