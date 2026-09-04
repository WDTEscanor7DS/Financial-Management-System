<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeneralLedgerTest extends TestCase
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

    public function test_balanced_entry_is_created_successfully(): void
    {
        $accountant = $this->accountant();

        $cash = ChartOfAccount::create(['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'normal_balance' => 'Debit']);
        $expense = ChartOfAccount::create(['account_code' => '5000', 'account_name' => 'Operating Expense', 'account_type' => 'Expense', 'normal_balance' => 'Debit']);

        $response = $this->actingAs($accountant)->postJson('/api/general-ledger/entries', [
            'entry_date' => now()->toDateString(),
            'description' => 'Test balanced entry',
            'lines' => [
                ['account_id' => $expense->id, 'debit' => 500, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 500],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('journal_entries', ['description' => 'Test balanced entry']);
        $this->assertDatabaseCount('journal_entry_lines', 2);
    }

    public function test_unbalanced_entry_is_rejected(): void
    {
        $accountant = $this->accountant();

        $cash = ChartOfAccount::create(['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'normal_balance' => 'Debit']);
        $expense = ChartOfAccount::create(['account_code' => '5000', 'account_name' => 'Operating Expense', 'account_type' => 'Expense', 'normal_balance' => 'Debit']);

        $response = $this->actingAs($accountant)->postJson('/api/general-ledger/entries', [
            'entry_date' => now()->toDateString(),
            'description' => 'Test unbalanced entry',
            'lines' => [
                ['account_id' => $expense->id, 'debit' => 500, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 300],
            ],
        ]);

        $response->assertStatus(500);
        $this->assertDatabaseMissing('journal_entries', ['description' => 'Test unbalanced entry']);
    }

    public function test_reversal_creates_offsetting_entry_and_blocks_double_reversal(): void
    {
        $accountant = $this->accountant();

        $cash = ChartOfAccount::create(['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'normal_balance' => 'Debit']);
        $expense = ChartOfAccount::create(['account_code' => '5000', 'account_name' => 'Operating Expense', 'account_type' => 'Expense', 'normal_balance' => 'Debit']);

        $create = $this->actingAs($accountant)->postJson('/api/general-ledger/entries', [
            'entry_date' => now()->toDateString(),
            'description' => 'Original entry',
            'lines' => [
                ['account_id' => $expense->id, 'debit' => 500, 'credit' => 0],
                ['account_id' => $cash->id, 'debit' => 0, 'credit' => 500],
            ],
        ]);
        $entryId = $create->json('data.raw_id');

        $reverse = $this->actingAs($accountant)->postJson("/api/general-ledger/entries/{$entryId}/reverse");
        $reverse->assertStatus(201);
        $this->assertDatabaseCount('journal_entries', 2);

        $secondReverse = $this->actingAs($accountant)->postJson("/api/general-ledger/entries/{$entryId}/reverse");
        $secondReverse->assertStatus(500);
        $this->assertDatabaseCount('journal_entries', 2);
    }
}