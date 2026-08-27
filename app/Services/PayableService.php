<?php

namespace App\Services;

use App\Models\AccountsPayable;
use App\Models\ApPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayableService
{
    public function create(array $data, int $userId): AccountsPayable
    {
        return DB::transaction(function () use ($data, $userId) {
            $payable = AccountsPayable::create([
                ...$data,
                'amount_paid' => 0,
                'balance' => $data['amount'],
                'status' => 'Pending',
                'created_by' => $userId,
            ]);

            AuditService::log('Created Payable', 'Accounts Payable', (string) $payable->id);

            return $payable;
        });
    }

    /**
     * Section 21: this is the canonical "AP payment" transaction from the
     * brief. The payable row is locked for the duration of the transaction
     * so two simultaneous payments against the same invoice cannot both
     * read the same starting balance and jointly overpay it.
     */
    public function recordPayment(int $payableId, float $amount, int $userId): AccountsPayable
    {
        return DB::transaction(function () use ($payableId, $amount, $userId) {
            /** @var AccountsPayable $payable */
            $payable = AccountsPayable::query()->lockForUpdate()->findOrFail($payableId);

            if ($payable->status === 'Paid') {
                throw ValidationException::withMessages([
                    'amount' => 'This invoice has already been paid in full.',
                ]);
            }

            if ($amount > (float) $payable->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount cannot exceed the outstanding balance.',
                ]);
            }

            ApPayment::create([
                'accounts_payable_id' => $payable->id,
                'amount' => $amount,
                'paid_at' => now()->toDateString(),
                'recorded_by' => $userId,
            ]);

            $newPaid = (float) $payable->amount_paid + $amount;
            $newBalance = max(0, (float) $payable->amount - $newPaid);

            $payable->update([
                'amount_paid' => $newPaid,
                'balance' => $newBalance,
                'status' => $newBalance <= 0 ? 'Paid' : 'Partially Paid',
            ]);

            AuditService::log(
                'Recorded AP Payment',
                'Accounts Payable',
                (string) $payable->id,
                sprintf('Payment of \u20b1%s', number_format($amount, 2))
            );
            NotificationService::push(sprintf(
                'Payment recorded for AP-%05d (\u20b1%s).',
                $payable->id,
                number_format($amount, 2)
            ));

            return $payable->refresh();
        });
    }

    public function delete(AccountsPayable $payable): void
    {
        DB::transaction(function () use ($payable) {
            $id = $payable->id;
            $payable->delete(); // cascades to ap_payments per migration
            AuditService::log('Deleted Payable', 'Accounts Payable', (string) $id);
        });
    }
}
