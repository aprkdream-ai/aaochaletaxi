<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\Finance\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public function __construct(private InvoiceService $service) {}

    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:40'], 'type' => ['nullable', 'in:invoice,credit_note']]);

        return view('operator.finance.invoices', [
            'invoices' => Invoice::query()->with('booking:id,booking_number,public_token')
                ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('type', $t))
                ->when($filters['q'] ?? null, fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', '%'.addcslashes($t, '%_\\').'%')->orWhereHas('booking', fn ($b) => $b->where('booking_number', 'like', '%'.addcslashes($t, '%_\\').'%'))))
                ->latest('id')->paginate(30)->withQueryString(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        $request->merge(['gstin' => $request->filled('gstin') ? strtoupper(trim($request->input('gstin'))) : null]);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'gstin' => ['nullable', 'regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:60'],
            'inter_state' => ['nullable', 'boolean'],
        ], ['gstin.regex' => __('Enter a valid 15-character GSTIN.')]);
        $invoice = $this->service->issue($booking, $request->user(), $data + ['inter_state' => $request->boolean('inter_state')]);

        return redirect()->route('operator.invoices.show', $invoice)->with('success', __('Invoice :no issued.', ['no' => $invoice->number]));
    }

    public function show(Invoice $invoice): View
    {
        return view('shared.booking-invoice', ['invoice' => $invoice->load(['items', 'booking', 'original']), 'pdf' => false, 'backUrl' => route('operator.bookings.show', $invoice->booking)]);
    }

    public function pdf(Invoice $invoice): Response
    {
        return self::renderPdf($invoice);
    }

    public function creditNote(Request $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $cn = $this->service->creditNote($invoice, $data['reason'], $request->user());

        return redirect()->route('operator.invoices.show', $cn)->with('success', __('Credit note :no issued. You can now issue a corrected invoice.', ['no' => $cn->number]));
    }

    public static function renderPdf(Invoice $invoice): Response
    {
        $invoice->load(['items', 'booking', 'original']);

        return Pdf::loadView('shared.booking-invoice', ['invoice' => $invoice, 'pdf' => true, 'backUrl' => null])
            ->setPaper('a4')->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false])
            ->download(str_replace('/', '-', $invoice->number).'.pdf');
    }
}
