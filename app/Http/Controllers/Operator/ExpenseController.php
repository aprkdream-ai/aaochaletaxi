<?php

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Services\AuditLogger;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public const CATEGORIES = ['rent' => 'Office rent', 'salaries' => 'Staff salaries', 'utilities' => 'Electricity & internet', 'marketing' => 'Marketing',
        'software' => 'Software & phone', 'office' => 'Office supplies', 'professional' => 'CA / legal fees', 'taxes' => 'Taxes & fees', 'other' => 'Other'];

    public function index(Request $request): View
    {
        $month = $request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $expenses = Expense::query()->with(['branch:id,name', 'recorder:id,name'])->whereDate('expense_date', '>=', $start->toDateString())->whereDate('expense_date', '<=', $start->copy()->endOfMonth()->toDateString())->orderByDesc('expense_date')->orderByDesc('id')->get();

        return view('operator.finance.expenses', [
            'expenses' => $expenses, 'month' => $month, 'total' => $expenses->sum('amount'),
            'byCategory' => $expenses->groupBy('category')->map->sum('amount')->sortDesc(),
            'branches' => Branch::query()->active()->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, TenantContext $context, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'amount' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'payment_method' => ['nullable', 'in:cash,upi,card,bank_transfer,cheque,other'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $context->id())],
        ]);
        $e = new Expense($data);
        $e->recorded_by = $request->user()->id;
        $e->save();
        $audit->log('expense.recorded', $e, [], $data);

        return back()->with('success', __('Expense recorded.'));
    }

    public function destroy(Expense $expense, AuditLogger $audit): RedirectResponse
    {
        $audit->log('expense.deleted', $expense, $expense->only(['expense_date', 'category', 'amount']), []);
        $expense->delete();

        return back()->with('success', __('Expense deleted.'));
    }
}
