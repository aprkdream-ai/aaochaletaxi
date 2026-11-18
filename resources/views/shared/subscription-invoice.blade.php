@php
    use App\Support\Money;
    $snap = $payment->billing_snapshot ?? [];
    $seller = $snap['seller'] ?? [];
    $buyer = $snap['buyer'] ?? [];
    $item = $snap['item'] ?? [];
    $sameState = filled($seller['state'] ?? null) && filled($buyer['state'] ?? null) && strcasecmp(trim($seller['state']), trim($buyer['state'])) === 0;
    $halfTax = Money::round(bcdiv((string) $payment->tax_amount, '2', 4));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
    <title>{{ __('Tax invoice') }} {{ $payment->invoice_number }}</title>
    <style>
        *{box-sizing:border-box} body{margin:0;background:#eef1f6;font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:#1a2233}
        .sheet{max-width:820px;margin:24px auto;background:#fff;padding:40px;border-radius:12px;box-shadow:0 8px 30px rgba(11,18,32,.08)}
        .top{display:flex;justify-content:space-between;gap:24px;border-bottom:3px solid #ffb703;padding-bottom:20px;margin-bottom:24px}
        .brand{font-weight:800;font-size:22px;letter-spacing:-.02em}.brand span{color:#e09e00}
        h1{font-size:18px;margin:0 0 4px;text-transform:uppercase;letter-spacing:.08em}
        .muted{color:#5b6476}.grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px}
        .label{font-size:11px;text-transform:uppercase;letter-spacing:.1em;color:#5b6476;font-weight:700;margin-bottom:4px}
        table{width:100%;border-collapse:collapse} th,td{padding:10px 8px;border-bottom:1px solid #e6e9f0;text-align:left} th{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#5b6476}
        .r{text-align:right}.total td{font-weight:800;font-size:16px;border-bottom:0;border-top:2px solid #0b1220}
        .foot{margin-top:28px;font-size:12px;color:#5b6476;white-space:pre-line}
        .actions{max-width:820px;margin:16px auto 0;text-align:right}
        .btn{display:inline-block;background:#0b1220;color:#fff;padding:10px 18px;border-radius:8px;border:0;font-weight:700;cursor:pointer}
        @media print{body{background:#fff}.sheet{box-shadow:none;margin:0;max-width:none;padding:0}.actions{display:none}}
        @media (max-width:600px){.sheet{padding:20px;margin:0;border-radius:0}.grid,.top{grid-template-columns:1fr;display:block}.top>div+div{margin-top:12px}}
    </style>
</head>
<body>
<div class="actions"><button class="btn" onclick="window.print()">{{ __('Print / Save as PDF') }}</button></div>
<main class="sheet">
    <div class="top">
        <div>
            <div class="brand">AaoChale<span>Taxi</span></div>
            <div><b>{{ $seller['name'] ?? 'AaoChaleTaxi' }}</b></div>
            @if (! empty($seller['address']))<div class="muted" style="white-space:pre-line">{{ $seller['address'] }}</div>@endif
            @if (! empty($seller['gstin']))<div>GSTIN: <b>{{ $seller['gstin'] }}</b></div>@endif
            @if (! empty($seller['pan']))<div class="muted">PAN: {{ $seller['pan'] }}</div>@endif
        </div>
        <div style="text-align:right">
            <h1>{{ ! empty($seller['gstin']) ? __('Tax invoice') : __('Invoice') }}</h1>
            <div><span class="muted">{{ __('Invoice no.') }}</span> <b>{{ $payment->invoice_number }}</b></div>
            <div><span class="muted">{{ __('Date') }}</span> {{ $payment->paid_at?->format('d M Y') }}</div>
            <div><span class="muted">{{ __('Status') }}</span> <b style="color:#12a150">{{ strtoupper($payment->status) }}</b></div>
        </div>
    </div>
    <div class="grid">
        <div>
            <div class="label">{{ __('Billed to') }}</div>
            <div><b>{{ $buyer['name'] ?? '' }}</b></div>
            @if (! empty($buyer['address']))<div class="muted">{{ $buyer['address'] }}</div>@endif
            @if (! empty($buyer['gstin']))<div>GSTIN: <b>{{ $buyer['gstin'] }}</b></div>@endif
            <div class="muted">{{ $buyer['email'] ?? '' }} · {{ $buyer['phone'] ?? '' }}</div>
        </div>
        <div>
            <div class="label">{{ __('Payment') }}</div>
            <div>{{ $payment->method->label() }}@if ($payment->reference) · {{ $payment->reference }}@endif</div>
            @if (! empty($buyer['state']))<div class="muted">{{ __('Place of supply') }}: {{ $buyer['state'] }}</div>@endif
        </div>
    </div>
    <table>
        <thead><tr><th>{{ __('Description') }}</th><th>SAC</th><th class="r">{{ __('Amount') }}</th></tr></thead>
        <tbody>
            <tr><td><b>{{ __('AaoChaleTaxi :plan plan', ['plan' => $item['plan'] ?? $payment->subscription?->plan?->name]) }}</b> <span class="muted">({{ ucfirst($item['cycle'] ?? '') }})</span><div class="muted">{{ $item['period'] ?? '' }}</div></td><td>{{ $item['sac'] ?? '998314' }}</td><td class="r">{{ Money::format($payment->amount) }}</td></tr>
            @if ((float) $payment->tax_amount > 0)
                @if ($sameState)
                    <tr><td colspan="2" class="r muted">CGST @ {{ rtrim(rtrim(bcdiv((string) $payment->tax_percent, '2', 2), '0'), '.') }}%</td><td class="r">{{ Money::format($halfTax) }}</td></tr>
                    <tr><td colspan="2" class="r muted">SGST @ {{ rtrim(rtrim(bcdiv((string) $payment->tax_percent, '2', 2), '0'), '.') }}%</td><td class="r">{{ Money::format(Money::sub($payment->tax_amount, $halfTax)) }}</td></tr>
                @else
                    <tr><td colspan="2" class="r muted">IGST @ {{ rtrim(rtrim((string) $payment->tax_percent, '0'), '.') }}%</td><td class="r">{{ Money::format($payment->tax_amount) }}</td></tr>
                @endif
            @endif
            <tr class="total"><td colspan="2" class="r">{{ __('Total') }}</td><td class="r">{{ Money::format($payment->total) }}</td></tr>
        </tbody>
    </table>
    <div class="foot">@if (! empty($seller['bank'])){{ $seller['bank'] }}

@endif{{ __('This is a computer-generated invoice and does not require a signature.') }} {{ __('Questions? :email', ['email' => $seller['email'] ?? config('aaochale.support_email')]) }}</div>
</main>
</body>
</html>
