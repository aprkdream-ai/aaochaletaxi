@php
    use App\Support\Money;
    $s = $invoice->seller; $b = $invoice->buyer;
    $logo = (! $pdf && ! empty($s['logo'])) ? \Illuminate\Support\Facades\Storage::disk('public')->url($s['logo']) : null;
    $pct = rtrim(rtrim((string) $invoice->gst_percent, '0'), '.');
    $half = rtrim(rtrim(number_format((float) $invoice->gst_percent / 2, 2, '.', ''), '0'), '.');
    $words = (function (float $n) {
        $f = new \NumberFormatter('en_IN', \NumberFormatter::SPELLOUT);
        $r = (int) floor($n); $p = (int) round(($n - $r) * 100);
        return ucfirst($f->format($r)).' rupees'.($p ? ' and '.$f->format($p).' paise' : '').' only';
    })((float) $invoice->total);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>{{ $invoice->title() }} {{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "DejaVu Sans", system-ui, -apple-system, "Segoe UI", sans-serif; font-size: 12px; color: #1a2233; background: {{ $pdf ? '#fff' : '#eef1f6' }}; }
        .sheet { max-width: 820px; margin: {{ $pdf ? '0' : '24px auto' }}; background: #fff; padding: {{ $pdf ? '0' : '36px' }}; border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; }
        .top td { vertical-align: top; }
        h1 { font-size: 18px; margin: 0 0 4px; letter-spacing: 1px; text-transform: uppercase; }
        .muted { color: #5b6476; } .r { text-align: right; } .b { font-weight: bold; }
        .bar { height: 4px; background: #ffb703; margin: 14px 0 16px; }
        .label { font-size: 10px; text-transform: uppercase; letter-spacing: 1px; color: #5b6476; font-weight: bold; margin-bottom: 3px; }
        .items th, .items td { padding: 7px 6px; border-bottom: 1px solid #e6e9f0; text-align: left; }
        .items th { font-size: 10px; text-transform: uppercase; color: #5b6476; background: #f6f7f9; }
        .items .r { text-align: right; }
        .tot td { padding: 4px 6px; } .grand td { font-size: 15px; font-weight: bold; border-top: 2px solid #0b1220; padding-top: 8px; }
        .box { border: 1px solid #e6e9f0; border-radius: 6px; padding: 10px; }
        .stamp { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .actions { max-width: 820px; margin: 16px auto 0; text-align: right; }
        .btn { display: inline-block; background: #0b1220; color: #fff; padding: 9px 16px; border-radius: 8px; text-decoration: none; font-weight: bold; margin-left: 6px; border: 0; cursor: pointer; font-size: 13px; }
        .btn.light { background: #fff; color: #0b1220; border: 1px solid #d5dae4; }
        @media print { body { background: #fff; } .sheet { margin: 0; padding: 0; } .actions { display: none; } }
        @media (max-width: 600px) { .sheet { padding: 16px; margin: 0; border-radius: 0; } }
    </style>
</head>
<body>
@unless ($pdf)
    <div class="actions">
        @if ($backUrl)<a class="btn light" href="{{ $backUrl }}">← {{ __('Back') }}</a>@endif
        <a class="btn light" href="{{ route('operator.invoices.pdf', $invoice) }}">{{ __('Download PDF') }}</a>
        <button class="btn" onclick="window.print()">{{ __('Print') }}</button>
    </div>
@endunless
<div class="sheet">
    <table class="top"><tr>
        <td style="width:60%">
            @if ($logo)<img src="{{ $logo }}" alt="" style="height:44px;margin-bottom:6px"><br>@endif
            <div class="b" style="font-size:15px">{{ $s['name'] }}</div>
            @if (($s['brand'] ?? null) && $s['brand'] !== $s['name'])<div class="muted">{{ $s['brand'] }}</div>@endif
            <div class="muted">{{ $s['address'] }}</div>
            <div class="muted">{{ $s['phone'] }} · {{ $s['email'] }}</div>
            @if (! empty($s['gstin']))<div>GSTIN: <span class="b">{{ $s['gstin'] }}</span></div>@endif
            @if (! empty($s['pan']))<div class="muted">PAN: {{ $s['pan'] }}</div>@endif
        </td>
        <td class="r">
            <h1>{{ $invoice->title() }}</h1>
            <div><span class="muted">{{ __('No.') }}</span> <span class="b">{{ $invoice->number }}</span></div>
            <div><span class="muted">{{ __('Date') }}</span> {{ $invoice->invoice_date->format('d M Y') }}</div>
            <div><span class="muted">{{ __('Booking') }}</span> {{ $invoice->booking->booking_number }}</div>
            @if ($invoice->isCreditNote() && $invoice->original)<div><span class="muted">{{ __('Against invoice') }}</span> {{ $invoice->original->number }}</div>@endif
            @if ($invoice->status === 'credited')<div style="margin-top:6px"><span class="stamp" style="background:#fde4e5;color:#b4232a">{{ __('CANCELLED BY CREDIT NOTE') }}</span></div>@endif
        </td>
    </tr></table>
    <div class="bar"></div>
    <table class="top"><tr>
        <td style="width:50%;padding-right:10px"><div class="box">
            <div class="label">{{ __('Billed to') }}</div>
            <div class="b">{{ $b['name'] }}</div>
            @if (! empty($b['address']))<div class="muted">{{ $b['address'] }}</div>@endif
            <div class="muted">{{ $b['phone'] }}@if (! empty($b['email'])) · {{ $b['email'] }}@endif</div>
            @if (! empty($b['gstin']))<div>GSTIN: <span class="b">{{ $b['gstin'] }}</span></div>@endif
        </div></td>
        <td style="width:50%;padding-left:10px"><div class="box">
            <div class="label">{{ __('Supply') }}</div>
            <div>{{ __('Place of supply') }}: {{ $invoice->place_of_supply ?: '—' }}</div>
            <div class="muted">{{ $invoice->notes }}</div>
        </div></td>
    </tr></table>
    <table class="items" style="margin-top:16px">
        <thead><tr><th style="width:5%">#</th><th>{{ __('Description') }}</th><th style="width:12%">SAC</th><th class="r" style="width:20%">{{ __('Amount (₹)') }}</th></tr></thead>
        <tbody>
            @foreach ($invoice->items as $i => $item)
                <tr><td>{{ $i + 1 }}</td><td>{{ $item->description }}@unless ($item->taxable)<div class="muted" style="font-size:10px">{{ __('Not part of taxable value') }}</div>@endunless</td><td>{{ $item->sac ?? '—' }}</td><td class="r">{{ number_format((float) $item->amount, 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>
    <table style="margin-top:8px"><tr>
        <td style="width:55%;vertical-align:bottom" class="muted"><div class="label">{{ __('Amount in words') }}</div>{{ $words }}</td>
        <td><table class="tot">
            <tr><td class="muted">{{ __('Taxable value') }}</td><td class="r">{{ Money::format($invoice->taxable) }}</td></tr>
            @if ($invoice->inter_state)
                <tr><td class="muted">IGST @ {{ $pct }}%</td><td class="r">{{ Money::format($invoice->igst) }}</td></tr>
            @else
                <tr><td class="muted">CGST @ {{ $half }}%</td><td class="r">{{ Money::format($invoice->cgst) }}</td></tr>
                <tr><td class="muted">SGST @ {{ $half }}%</td><td class="r">{{ Money::format($invoice->sgst) }}</td></tr>
            @endif
            @if ((float) $invoice->reimbursements > 0)<tr><td class="muted">{{ __('Reimbursements') }}</td><td class="r">{{ Money::format($invoice->reimbursements) }}</td></tr>@endif
            @if ((float) $invoice->round_off != 0)<tr><td class="muted">{{ __('Round off') }}</td><td class="r">{{ Money::format($invoice->round_off) }}</td></tr>@endif
            <tr class="grand"><td>{{ __('Total') }}</td><td class="r">{{ Money::format($invoice->total) }}</td></tr>
        </table></td>
    </tr></table>
    <div class="muted" style="margin-top:22px;font-size:10px;line-height:1.5">
        @if (! empty($s['gstin']))GST is charged at {{ $pct }}% on passenger transport / rent-a-cab services. @endif Tolls, parking and state taxes are reimbursements collected at actuals and do not form part of the taxable value.
        This is a computer-generated document and does not require a signature. Generated via AaoChaleTaxi.
    </div>
</div>
</body>
</html>
