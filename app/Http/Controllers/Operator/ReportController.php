<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Services\Finance\ReportService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request, TenantContext $context): View
    {
        [$from, $to, $tab] = $this->params($request);
        $advanced = (bool) $context->get()->usableSubscription()?->plan->advanced_reports;
        if (in_array($tab, ['vehicles', 'bookings'], true) && ! $advanced) {
            $tab = 'locked';
        }
        $r = ReportService::for($from, $to);

        return view('operator.finance.reports', [
            'from' => $from, 'to' => $to, 'tab' => $tab, 'advanced' => $advanced,
            'summary' => $tab === 'summary' ? $r->summary() : null,
            'daily' => $tab === 'summary' ? $r->daily() : null,
            'vehicles' => $tab === 'vehicles' ? $r->vehicles() : null,
            'bookings' => $tab === 'bookings' ? $r->bookings() : null,
            'gst' => $tab === 'gst' ? $r->gst() : null,
        ]);
    }

    /** CSV (opens in Excel; UTF-8 BOM keeps ₹ and Hindi intact). */
    public function export(Request $request, TenantContext $context): StreamedResponse
    {
        [$from, $to, $tab] = $this->params($request);
        abort_if(in_array($tab, ['vehicles', 'bookings'], true) && ! $context->get()->usableSubscription()?->plan->advanced_reports, 403);
        $r = ReportService::for($from, $to);

        [$head, $rows] = match ($tab) {
            'vehicles' => [['Vehicle', 'Category', 'Trips', 'Km', 'Revenue', 'Expenses', 'Maintenance', 'Profit'],
                $r->vehicles()->map(fn ($x) => [$x->vehicle->displayRegistration(), $x->vehicle->category?->name, $x->trips, $x->km, $x->revenue, $x->expenses, $x->maintenance, $x->profit])],
            'bookings' => [['Booking', 'Completed', 'Customer', 'Phone', 'Type', 'Vehicle', 'Driver', 'Km', 'Revenue', 'Driver allowance', 'GST', 'Reimbursements', 'Total', 'Paid', 'Balance'],
                $r->bookings()->map(fn ($x) => [$x->booking->booking_number, $x->booking->trip->completed_at->format('Y-m-d'), $x->booking->customer_name, $x->booking->customer_phone, $x->booking->type->label(),
                    $x->booking->trip->vehicle?->registration_number, $x->booking->trip->driver?->name, $x->km, $x->revenue, $x->allowance, $x->gst, $x->reimbursements, $x->total, $x->paid, $x->balance])],
            'gst' => [['Date', 'Number', 'Type', 'Customer', 'Customer GSTIN', 'Taxable', 'CGST', 'SGST', 'IGST', 'Reimbursements', 'Total'],
                $r->gst()['documents']->map(fn ($i) => [$i->invoice_date->format('Y-m-d'), $i->number, $i->isCreditNote() ? 'Credit note' : 'Invoice', $i->buyer['name'] ?? '', $i->buyer['gstin'] ?? '',
                    ($i->isCreditNote() ? '-' : '').$i->taxable, ($i->isCreditNote() ? '-' : '').$i->cgst, ($i->isCreditNote() ? '-' : '').$i->sgst, ($i->isCreditNote() ? '-' : '').$i->igst, ($i->isCreditNote() ? '-' : '').$i->reimbursements, ($i->isCreditNote() ? '-' : '').$i->total])],
            default => [['Date', 'Revenue', 'Collections'], collect($r->daily())->map(fn ($v, $d) => [$d, $v['revenue'], $v['collections']])->values()],
        };

        $name = sprintf('aaochaletaxi-%s-%s-to-%s.csv', $tab, $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($head, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $head);
            foreach ($rows as $row) {
                // Neutralise spreadsheet formulas (CSV injection).
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) && ! is_numeric($v) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function params(Request $request): array
    {
        $d = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from'], 'tab' => ['nullable', 'in:summary,vehicles,bookings,gst']]);
        $from = isset($d['from']) ? Carbon::parse($d['from']) : now()->startOfMonth();
        $to = isset($d['to']) ? Carbon::parse($d['to']) : now();
        if ($from->diffInDays($to) > 366) {
            $from = $to->copy()->subYear();
        }

        return [$from, $to, $d['tab'] ?? 'summary'];
    }
}
