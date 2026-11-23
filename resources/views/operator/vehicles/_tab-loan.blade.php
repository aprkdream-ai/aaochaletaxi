@php use App\Support\Money; $svc = app(\App\Services\VehicleLoanService::class); @endphp
@foreach ($vehicle->loans as $loan)
    @php $schedule = $loanSchedules[$loan->id]; $paid = collect($schedule)->whereNotNull('expense')->count(); $next = collect($schedule)->firstWhere('expense', null); @endphp
    <div class="card panel-card mb-3">
        <div class="card-header"><span>{{ $loan->lender }} @if ($loan->account_number)<span class="small text-muted-2">· {{ $loan->account_number }}</span>@endif</span>
            <span class="badge-soft {{ $loan->status === 'active' ? 'info' : 'muted' }}">{{ $loan->status === 'active' ? __('Active') : __('Closed :d', ['d' => $loan->closed_on?->format('d M Y')]) }}</span></div>
        <div class="card-body border-bottom"><div class="row g-3 small">
            <div class="col-6 col-md-3"><div class="text-muted-2">{{ __('Principal') }}</div><div class="fw-bold">{{ Money::format($loan->principal, 0) }}</div></div>
            <div class="col-6 col-md-3"><div class="text-muted-2">{{ __('EMI') }}</div><div class="fw-bold">{{ Money::format($loan->emi_amount) }} <span class="fw-normal text-muted-2">× {{ $loan->tenure_months }}</span></div></div>
            <div class="col-6 col-md-3"><div class="text-muted-2">{{ __('Paid') }}</div><div class="fw-bold">{{ $paid }} / {{ $loan->tenure_months }}</div></div>
            <div class="col-6 col-md-3"><div class="text-muted-2">{{ __('Outstanding (EMIs left)') }}</div><div class="fw-bold">{{ Money::format(Money::mul((string) ($loan->tenure_months - $paid), (string) $loan->emi_amount), 0) }}</div></div>
        </div></div>
        @if ($loan->status === 'active' && $next && $canExpense)
            <form method="POST" action="{{ route('operator.loans.pay', $loan) }}" class="card-body border-bottom row g-2 align-items-end" data-loading>@csrf
                <input type="hidden" name="installment" value="{{ $next['no'] }}">
                <div class="col-md-3 small"><div class="text-muted-2">{{ __('Next EMI') }}</div><div class="fw-bold {{ $next['due']->isPast() && ! $next['due']->isToday() ? 'text-danger' : '' }}">#{{ $next['no'] }} · {{ $next['due']->format('d M Y') }}</div></div>
                <div class="col-6 col-md-2"><label class="form-label small" for="la{{ $loan->id }}">{{ __('Amount ₹') }}</label><input type="number" step="0.01" min="1" name="amount" id="la{{ $loan->id }}" value="{{ $loan->emi_amount }}" class="form-control form-control-sm" required></div>
                <div class="col-6 col-md-2"><label class="form-label small" for="ld{{ $loan->id }}">{{ __('Paid on') }}</label><input type="date" name="paid_on" id="ld{{ $loan->id }}" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                <div class="col-6 col-md-2"><label class="form-label small" for="lm{{ $loan->id }}">{{ __('Method') }}</label><select name="payment_method" id="lm{{ $loan->id }}" class="form-select form-select-sm"><option value="bank_transfer">{{ __('Bank / NACH') }}</option><option value="upi">UPI</option><option value="cash">{{ __('Cash') }}</option><option value="other">{{ __('Other') }}</option></select></div>
                <div class="col-6 col-md-3"><button class="btn btn-sm btn-primary w-100"><x-icon name="check2" />{{ __('Mark EMI paid') }}</button></div>
            </form>
        @endif
        <details class="card-body small"><summary class="text-muted-2">{{ __('EMI schedule') }}</summary>
            <div class="table-responsive mt-2"><table class="table table-sm small mb-0">
                <thead><tr><th>#</th><th>{{ __('Due') }}</th><th>{{ __('Status') }}</th></tr></thead>
                <tbody>@foreach ($schedule as $row)<tr><td>{{ $row['no'] }}</td><td>{{ $row['due']->format('d M Y') }}</td><td>@if ($row['expense'])<span class="text-success">{{ __('Paid :d · :a', ['d' => $row['expense']->expense_date->format('d M Y'), 'a' => Money::format($row['expense']->amount)]) }}</span>@elseif ($row['due']->lt(today()))<span class="text-danger">{{ __('Overdue') }}</span>@else — @endif</td></tr>@endforeach</tbody>
            </table></div>
            @if ($loan->status === 'active' && $canManage)
                <form method="POST" action="{{ route('operator.loans.close', $loan) }}" class="d-flex gap-2 mt-2" data-confirm="{{ __('Close this loan (prepaid / foreclosed)?') }}">@csrf
                    <input type="date" name="closed_on" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" style="max-width:180px" aria-label="{{ __('Closed on') }}">
                    <button class="btn btn-sm btn-light border">{{ __('Close loan') }}</button>
                </form>
            @endif
        </details>
    </div>
@endforeach
@error('installment')<div class="alert alert-danger small">{{ $message }}</div>@enderror
@if ($canManage)
    <details class="card panel-card" @if ($vehicle->loans->isEmpty() || $errors->hasAny(['lender', 'principal', 'tenure_months', 'first_emi_date'])) open @endif>
        <summary class="card-header">{{ __('Add a loan') }}</summary>
        <form method="POST" action="{{ route('operator.loans.store', $vehicle) }}" class="card-body row g-2" data-loading>@csrf
            <div class="col-md-4"><x-input name="lender" :label="__('Lender')" required maxlength="120" /></div>
            <div class="col-md-4"><x-input name="account_number" :label="__('Loan account no.')" maxlength="40" /></div>
            <div class="col-md-4"><x-input name="principal" type="number" step="1" min="1" :label="__('Loan amount ₹')" required /></div>
            <div class="col-6 col-md-3"><x-input name="interest_rate" type="number" step="0.01" min="0" max="40" :label="__('Interest % p.a.')" /></div>
            <div class="col-6 col-md-3"><x-input name="tenure_months" type="number" min="1" max="120" :label="__('Tenure (months)')" required /></div>
            <div class="col-6 col-md-3"><x-input name="emi_amount" type="number" step="0.01" min="1" :label="__('EMI ₹')" :help="__('Blank = calculate')" /></div>
            <div class="col-6 col-md-3"><x-input name="first_emi_date" type="date" :label="__('First EMI date')" required /></div>
            <div class="col-12"><button class="btn btn-primary"><x-icon name="plus-lg" />{{ __('Add loan') }}</button></div>
        </form>
    </details>
@elseif ($vehicle->loans->isEmpty())
    <div class="card panel-card"><x-empty-state icon="bank" :title="__('No loans')" /></div>
@endif
