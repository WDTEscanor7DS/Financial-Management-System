<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashBankTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function accountant(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', 'accountant')->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_deposit_increases_balance_and_posts_to_ledger(): void
    {
        $accountant = $this->accountant();
        $cash = ChartOfAccount::create(['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'normal_balance' => 'Debit']);
        $revenue = ChartOfAccount::create(['account_code' => '4000', 'account_name' => 'Revenue', 'account_type' => 'Revenue', 'normal_balance' => 'Credit']);
        $bankAccount = BankAccount::create([
            'account_name' => 'Test Account', 'account_type' => 'Bank',
            'chart_of_account_id' => $cash->id, 'opening_balance' => 1000, 'current_balance' => 1000,
        ]);

        $response = $this->actingAs($accountant)->postJson('/api/cash-bank/transactions', [
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => now()->toDateString(),
            'type' => 'Deposit',
            'amount' => 500,
            'description' => 'Test deposit',
            'contra_account_id' => $revenue->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bank_accounts', ['id' => $bankAccount->id, 'current_balance' => 1500]);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_withdrawal_exceeding_balance_is_rejected(): void
    {
        $accountant = $this->accountant();
        $cash = ChartOfAccount::create(['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'normal_balance' => 'Debit']);
        $expense = ChartOfAccount::create(['account_code' => '5000', 'account_name' => 'Expense', 'account_type' => 'Expense', 'normal_balance' => 'Debit']);
        $bankAccount = BankAccount::create([
            'account_name' => 'Test Account', 'account_type' => 'Bank',
            'chart_of_account_id' => $cash->id, 'opening_balance' => 1000, 'current_balance' => 1000,
        ]);

        $response = $this->actingAs($accountant)->postJson('/api/cash-bank/transactions', [
            'bank_account_id' => $bankAccount->id,
            'transaction_date' => now()->toDateString(),
            'type' => 'Withdrawal',
            'amount' => 5000,
            'description' => 'Too much',
            'contra_account_id' => $expense->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('bank_accounts', ['id' => $bankAccount->id, 'current_balance' => 1000]);
        $this->assertDatabaseCount('journal_entries', 0);
    }

    public function test_transfer_moves_balance_between_accounts(): void
    {
        $accountant = $this->accountant();
        $cash = ChartOfAccount::create(['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'normal_balance' => 'Debit']);
        $from = BankAccount::create([
            'account_name' => 'From Account', 'account_type' => 'Bank',
            'chart_of_account_id' => $cash->id, 'opening_balance' => 1000, 'current_balance' => 1000,
        ]);
        $to = BankAccount::create([
            'account_name' => 'To Account', 'account_type' => 'Bank',
            'chart_of_account_id' => $cash->id, 'opening_balance' => 0, 'current_balance' => 0,
        ]);

        $response = $this->actingAs($accountant)->postJson('/api/cash-bank/transactions', [
            'bank_account_id' => $from->id,
            'transaction_date' => now()->toDateString(),
            'type' => 'Transfer',
            'amount' => 300,
            'description' => 'Test transfer',
            'transfer_to_account_id' => $to->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bank_accounts', ['id' => $from->id, 'current_balance' => 700]);
        $this->assertDatabaseHas('bank_accounts', ['id' => $to->id, 'current_balance' => 300]);
    }
}