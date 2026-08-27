<?php

namespace App\Services;

use App\Models\AccountsReceivable;
use App\Models\ArPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReceivableService
{
    public function create(array $data, int $userId): AccountsReceivable
    {
        return DB::transaction(function () use ($data, $userId) {
            $receivable = AccountsReceivable::create([
                ...$data,
                'amount_paid' => 0,
                'balance' => $data['amount'],
                'status' => 'Outstanding',
                'created_by' => $userId,
            ]);

            AuditService::log('Created Receivable', 'Accounts Receivable', (string) $receivable->id);

            return $receivable;
        });
    }

    public function recordPayment(int $receivableId, float $amount, int $userId): AccountsReceivable
    {
        return DB::transaction(function () use ($receivableId, $amount, $userId) {
            /** @var AccountsReceivable $receivable */
            $receivable = AccountsReceivable::query()->lockForUpdate()->findOrFail($receivableId);

            if ($receivable->status === 'Paid') {
                throw ValidationException::withMessages([
                    'amount' => 'This receivable has already been fully collected.',
                ]);
            }

            if ($amount > (float) $receivable->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'Collection amount cannot exceed the outstanding balance.',
                ]);
            }

            ArPayment::create([
                'accounts_receivable_id' => $receivable->id,
                'amount' => $amount,
                'paid_at' => now()->toDateString(),
                'recorded_by' => $userId,
            ]);

            $newPaid = (float) $receivable->amount_paid + $amount;
            $newBalance = max(0, (float) $receivable->amount - $newPaid);

            $receivable->update([
                'amount_paid' => $newPaid,
                'balance' => $newBalance,
                'status' => $newBalance <= 0 ? 'Paid' : 'Partially Paid',
            ]);

            AuditService::log(
                'Recorded AR Payment',
                'Accounts Receivable',
                (string) $receivable->id,
                sprintf('Collection of \u20b1%s', number_format($amount, 2))
            );
            NotificationService::push(sprintf(
                'Collection recorded for AR-%05d (\u20b1%s).',
                $receivable->id,
                number_format($amount, 2)
            ));

            return $receivable->refresh();
        });
    }

    public function delete(AccountsReceivable $receivable): void
    {
        DB::transaction(function () use ($receivable) {
            $id = $receivable->id;
            $receivable->delete();
            AuditService::log('Deleted Receivable', 'Accounts Receivable', (string) $id);
        });
    }
}
