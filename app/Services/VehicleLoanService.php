<?php

namespace App\Services;

use App\Models\User;
use App\Models\VehicleExpense;
use App\Models\VehicleLoan;
use App\Support\Money;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Vehicle loans: EMI schedule, paying an installment (creates an `emi` vehicle expense), auto-close when fully paid. */
class VehicleLoanService
{
    public function __construct(private AuditLogger $audit) {}

    /** Reducing-balance EMI: P·r·(1+r)^n / ((1+r)^n − 1), r = monthly rate. */
    public static function emi(string $principal, string $annualRate, int $months): string
    {
        if ($months < 1) {
            return '0.00';
        }
        if (bccomp($annualRate, '0', 4) <= 0) {
            return Money::round(bcdiv($principal, (string) $months, 4));
        }
        $r = bcdiv($annualRate, '1200', 10);
        $pow = bcpow(bcadd('1', $r, 10), (string) $months, 10);

        return Money::round(bcdiv(bcmul(bcmul($principal, $r, 10), $pow, 10), bcsub($pow, '1', 10), 4));
    }

    public function create(array $data, User $user): VehicleLoan
    {
        if (empty($data['emi_amount'])) {
            $data['emi_amount'] = self::emi((string) $data['principal'], (string) ($data['interest_rate'] ?? 0), (int) $data['tenure_months']);
        }
        $loan = VehicleLoan::create($data);
        $this->audit->log('loan.created', $loan, [], ['lender' => $loan->lender, 'principal' => $loan->principal, 'emi' => $loan->emi_amount]);

        return $loan;
    }

    /** @return list<array{no:int, due:Carbon, expense:?VehicleExpense}> */
    public function schedule(VehicleLoan $loan): array
    {
        $paid = $loan->relationLoaded('payments') ? $loan->payments->keyBy('installment_no') : $loan->payments()->get()->keyBy('installment_no');

        return array_map(fn (int $n) => ['no' => $n, 'due' => $loan->dueDate($n), 'expense' => $paid->get($n)], range(1, $loan->tenure_months));
    }

    /** Lowest unpaid installment number, or null when fully paid. */
    public function nextInstallment(VehicleLoan $loan): ?int
    {
        $paid = $loan->payments()->pluck('installment_no')->all();
        foreach (range(1, $loan->tenure_months) as $n) {
            if (! in_array($n, $paid, true)) {
                return $n;
            }
        }

        return null;
    }

    public function outstanding(VehicleLoan $loan): string
    {
        $remaining = $loan->tenure_months - $loan->payments()->count();

        return Money::mul((string) max(0, $remaining), (string) $loan->emi_amount);
    }

    public function pay(VehicleLoan $loan, int $installment, string $amount, string $date, ?string $method, ?string $reference, User $user): VehicleExpense
    {
        try {
            return DB::transaction(function () use ($loan, $installment, $amount, $date, $method, $reference, $user) {
                $locked = VehicleLoan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
                if ($locked->status !== 'active') {
                    throw ValidationException::withMessages(['installment' => __('This loan is closed.')]);
                }
                if ($installment < 1 || $installment > $locked->tenure_months) {
                    throw ValidationException::withMessages(['installment' => __('Invalid installment.')]);
                }
                if ($locked->payments()->where('installment_no', $installment)->exists()) {
                    throw ValidationException::withMessages(['installment' => __('EMI :n is already paid.', ['n' => $installment])]);
                }
                $expense = new VehicleExpense([
                    'vehicle_id' => $locked->vehicle_id, 'expense_date' => $date, 'category' => 'emi', 'amount' => $amount,
                    'payment_method' => $method, 'reference' => $reference,
                    'notes' => __('EMI :n of :t — :lender', ['n' => $installment, 't' => $locked->tenure_months, 'lender' => $locked->lender]),
                ]);
                $expense->forceFill(['loan_id' => $locked->id, 'installment_no' => $installment, 'recorded_by' => $user->id])->save();
                if ($locked->payments()->count() >= $locked->tenure_months) {
                    $locked->forceFill(['status' => 'closed', 'closed_on' => $date])->save();
                }
                $this->audit->log('loan.emi_paid', $locked, [], ['installment' => $installment, 'amount' => $amount]);

                return $expense;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['installment' => __('EMI :n is already paid.', ['n' => $installment])]);
        }
    }

    /** Foreclose / close early (e.g. loan prepaid); history kept. */
    public function close(VehicleLoan $loan, string $date): void
    {
        $loan->forceFill(['status' => 'closed', 'closed_on' => $date])->save();
        $this->audit->log('loan.closed', $loan, [], ['on' => $date]);
    }
}
