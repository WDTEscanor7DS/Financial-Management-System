<?php

namespace App\Services;

use App\Models\Fund;
use App\Models\FundAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FundService
{
    public function create(array $data, int $userId): Fund
    {
        return DB::transaction(function () use ($data, $userId) {
            $fund = Fund::create([...$data, 'used' => 0, 'created_by' => $userId]);
            AuditService::log('Created Fund', 'Fund Management', (string) $fund->id);

            return $fund;
        });
    }

    /**
     * This is the exact scenario called out in Section 51 of the brief:
     *
     *   Available Fund = P100,000
     *   User A allocates P80,000
     *   User B allocates P80,000
     *   -> without protection, both reads see P100,000 remaining and both
     *      succeed, leaving the fund at -P60,000.
     *
     * lockForUpdate() takes a row-level lock on the fund for the life of
     * this transaction. If User A and User B's requests overlap, User B's
     * query blocks until User A's transaction commits (or rolls back), then
     * re-reads the now-updated `used` value -- so the second allocation is
     * correctly evaluated against P20,000 remaining, not the stale
     * P100,000, and is rejected.
     */
    public function allocate(int $fundId, float $amount, ?string $description, int $userId): Fund
    {
        return DB::transaction(function () use ($fundId, $amount, $description, $userId) {
            /** @var Fund $fund */
            $fund = Fund::query()->lockForUpdate()->findOrFail($fundId);

            $remaining = (float) $fund->allocation - (float) $fund->used;

            if ($amount > $remaining) {
                throw ValidationException::withMessages([
                    'amount' => 'Allocation exceeds the remaining balance available in this fund.',
                ]);
            }

            FundAllocation::create([
                'fund_id' => $fund->id,
                'amount' => $amount,
                'description' => $description,
                'allocated_by' => $userId,
            ]);

            $fund->update(['used' => (float) $fund->used + $amount]);

            AuditService::log(
                'Allocated Fund',
                'Fund Management',
                (string) $fund->id,
                sprintf('Allocated \u20b1%s', number_format($amount, 2))
            );

            return $fund->refresh();
        });
    }

    public function delete(Fund $fund): void
    {
        DB::transaction(function () use ($fund) {
            $id = $fund->id;
            $fund->delete();
            AuditService::log('Deleted Fund', 'Fund Management', (string) $id);
        });
    }
}
