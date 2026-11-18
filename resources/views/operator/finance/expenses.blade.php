@php use App\Support\Money; use App\Http\Controllers\Operator\ExpenseController as EC; @endphp
<x-layouts.panel :title="__('Expenses')" panel="operator">
    <x-page-header :title="__('Business expenses')" :subtitle="__('Rent, salaries, marketing and other costs (vehicle costs are on each vehicle)')">
        <form method="GET"><input type="month" name="month" value="{{ $month }}" class="form-control" onchange="this.form.submit()" aria-label="{{ __('Month') }}"></form>
    </x-page-header>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card panel-card mb-3"><div class="card-body">
                <form method="POST" action="{{ route('operator.expenses.store') }}" class="row g-2 align-items-end" data-loading>@csrf
                    <div class="col-6 col-md-2"><label class="form-label small" for="x_date">{{ __('Date') }}</label><input type="date" name="expense_date" id="x_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required></div>
                    <div class="col-6 col-md-3"><label class="form-label small" for="x_cat">{{ __('Category') }}</label><select name="category" id="x_cat" class="form-select form-select-sm">@foreach (EC::CATEGORIES as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="x_amt">{{ __('Amount ₹') }}</label><input type="number" name="amount" id="x_amt" min="1" step="0.01" class="form-control form-control-sm" required></div>
                    <div class="col-6 col-md-2"><label class="form-label small" for="x_pm">{{ __('Paid by') }}</label><select name="payment_method" id="x_pm" class="form-select form-select-sm">@foreach (['bank_transfer' => __('Bank'), 'upi' => 'UPI', 'cash' => __('Cash'), 'card' => __('Card'), 'cheque' => __('Cheque'), 'other' => __('Other')] as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="col-md-2"><label class="form-label small" for="x_notes">{{ __('Notes') }}</label><input type="text" name="notes" id="x_notes" maxlength="500" class="form-control form-control-sm"></div>
                    <div class="col-md-1"><button class="btn btn-sm btn-primary w-100" aria-label="{{ __('Add') }}"><x-icon name="plus-lg" /></button></div>
                </form>
                @foreach (['expense_date', 'amount', 'category'] as $f)@error($f)<div class="text-danger small mt-1">{{ $message }}</div>@enderror @endforeach
            </div></div>
            <div class="card panel-card"><div class="table-responsive"><table class="table table-modern align-middle small">
                <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Category') }}</th><th>{{ __('Notes') }}</th><th>{{ __('By') }}</th><th class="text-end">{{ __('Amount') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($expenses as $e)
                        <tr><td class="text-nowrap">{{ $e->expense_date->format('d M') }}</td><td>{{ __(EC::CATEGORIES[$e->category] ?? $e->category) }}</td><td>{{ $e->notes ?: '—' }}</td><td>{{ $e->recorder?->name }}</td><td class="text-end fw-semibold text-nowrap">{{ Money::format($e->amount) }}</td>
                            <td class="text-end"><form method="POST" action="{{ route('operator.expenses.destroy', $e) }}" data-confirm="{{ __('Delete this expense?') }}">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger" aria-label="{{ __('Delete') }}"><x-icon name="trash" /></button></form></td></tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state icon="wallet2" :title="__('No expenses this month')" /></td></tr>
                    @endforelse
                </tbody>
            </table></div></div>
        </div>
        <div class="col-lg-4">
            <div class="card panel-card"><div class="card-header">{{ __('This month') }} <span class="fw-800">{{ Money::format($total, 0) }}</span></div>
                <div class="card-body d-grid gap-2">
                    @forelse ($byCategory as $cat => $amt)
                        <div><div class="d-flex justify-content-between small"><span>{{ __(EC::CATEGORIES[$cat] ?? $cat) }}</span><b>{{ Money::format($amt, 0) }}</b></div>
                            <div class="progress" style="height:6px" role="progressbar" aria-label="{{ $cat }}" aria-valuenow="{{ $amt }}" aria-valuemax="{{ $total }}"><div class="progress-bar bg-warning" style="width: {{ $total > 0 ? $amt / $total * 100 : 0 }}%"></div></div></div>
                    @empty
                        <div class="small text-muted-2">{{ __('Nothing recorded yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.panel>
