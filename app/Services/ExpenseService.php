<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Expense;
use Illuminate\Support\Facades\DB;

/**
 * Mirrors the side effects that already existed in js/data.js
 * (createExpense/updateExpense/deleteExpense adjusting
 * budget.actualSpending), but with the two guarantees the frontend-only
 * version could never provide:
 *
 *  1. Atomicity: the expense row and the budget's actual_spending update
 *     happen inside one DB transaction, so a crash between the two never
 *     leaves the budget total out of sync with its expenses.
 *  2. Isolation: the budget row is pessimistically locked
 *     (lockForUpdate()) for the duration of the transaction, so two
 *     Accountants posting expenses against the same budget concurrently
 *     cannot both read the same "old" actual_spending and overwrite each
 *     other's increment (the classic lost-update race described in
 *     Section 51 of the brief, here applied to budgets instead of funds).
 */
class ExpenseService
{
    public function create(array $data, int $userId): Expense
    {
        return DB::transaction(function () use ($data, $userId) {
            $expense = Expense::create([...$data, 'recorded_by' => $userId]);

            if ($expense->budget_id) {
                $this->applyDelta($expense->budget_id, (float) $expense->amount);
            }

            AuditService::log('Created Expense', 'Expense & Disbursement', (string) $expense->id);
            NotificationService::push(sprintf(
                'New expense recorded: EXP-%05d (\u20b1%s)',
                $expense->id,
                number_format((float) $expense->amount, 2)
            ));

            return $expense;
        });
    }

    public function update(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            $oldBudgetId = $expense->budget_id;
            $oldAmount = (float) $expense->amount;

            $expense->update($data);
            $expense->refresh();

            $newBudgetId = $expense->budget_id;
            $newAmount = (float) $expense->amount;

            if ($oldBudgetId && $oldBudgetId !== $newBudgetId) {
                $this->applyDelta($oldBudgetId, -$oldAmount);
            }

            if ($newBudgetId) {
                $delta = $oldBudgetId === $newBudgetId ? ($newAmount - $oldAmount) : $newAmount;
                $this->applyDelta($newBudgetId, $delta);
            }

            AuditService::log('Updated Expense', 'Expense & Disbursement', (string) $expense->id);

            return $expense;
        });
    }

    public function delete(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $id = $expense->id;

            if ($expense->budget_id) {
                $this->applyDelta($expense->budget_id, -(float) $expense->amount);
            }

            $expense->delete();
            AuditService::log('Deleted Expense', 'Expense & Disbursement', (string) $id);
        });
    }

    private function applyDelta(int $budgetId, float $delta): void
    {
        /** @var Budget $budget */
        $budget = Budget::query()->lockForUpdate()->findOrFail($budgetId);
        $newActual = max(0, (float) $budget->actual_spending + $delta);
        $budget->update(['actual_spending' => $newActual]);

        $utilization = $budget->allocated > 0
            ? (int) round(($newActual / (float) $budget->allocated) * 100)
            : 0;

        if ($utilization >= 80) {
            NotificationService::push(sprintf(
                'Budget utilization for %s has reached %d%%.',
                $budget->category,
                $utilization
            ));
        }
    }
}
