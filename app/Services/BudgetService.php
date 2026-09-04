<?php

namespace App\Services;

use App\Models\Budget;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function create(array $data, int $userId): Budget
    {
        return DB::transaction(function () use ($data, $userId) {
            $budget = Budget::create([
                ...$data,
                'actual_spending' => 0,
                'created_by' => $userId,
            ]);

            AuditService::log('Created Budget', 'Budget Planning', (string) $budget->id);

            return $budget;
        });
    }

    public function update(Budget $budget, array $data): Budget
    {
        return DB::transaction(function () use ($budget, $data) {
            $budget->update($data);
            AuditService::log('Updated Budget', 'Budget Planning', (string) $budget->id);

            return $budget->refresh();
        });
    }

    public function delete(Budget $budget): void
    {
        DB::transaction(function () use ($budget) {
            $id = $budget->id;
            // restrictOnDelete() on expenses.budget_id (nullOnDelete, see
            // migration) means linked expenses simply lose their budget
            // link rather than blocking deletion or vanishing themselves --
            // financial history is preserved either way.
            $budget->delete();
            AuditService::log('Deleted Budget', 'Budget Planning', (string) $id);
        });
    }
}
