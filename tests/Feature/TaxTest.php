<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Role;
use App\Models\TaxType;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\TaxTypesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(TaxTypesSeeder::class);
    }

    private function accountant(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', 'accountant')->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_remittance_reduces_bank_balance_and_posts_to_ledger(): void
    {
        $accountant = $this->accountant();
        $cash = ChartOfAccount::create(['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'normal_balance' => 'Debit']);
        $bankAccount = BankAccount::create([
            'account_name' => 'Test Account', 'account_type' => 'Bank',
            'chart_of_account_id' => $cash->id, 'opening_balance' => 10000, 'current_balance' => 10000,
        ]);
        $taxType = TaxType::where('code', 'WTAX-COMP')->firstOrFail();

        $response = $this->actingAs($accountant)->postJson('/api/tax/remittances', [
            'tax_type_id' => $taxType->id,
            'period_covered' => 'September 2026',
            'remittance_date' => now()->toDateString(),
            'amount' => 2500,
            'bir_reference_no' => 'BIR-TEST-001',
            'bank_account_id' => $bankAccount->id,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('bank_accounts', ['id' => $bankAccount->id, 'current_balance' => 7500]);
        $this->assertDatabaseHas('tax_remittances', ['tax_type_id' => $taxType->id, 'amount' => 2500]);
        $this->assertDatabaseCount('journal_entries', 1);
    }

    public function test_remittance_exceeding_balance_is_rejected(): void
    {
        $accountant = $this->accountant();
        $cash = ChartOfAccount::create(['account_code' => '1000', 'account_name' => 'Cash', 'account_type' => 'Asset', 'normal_balance' => 'Debit']);
        $bankAccount = BankAccount::create([
            'account_name' => 'Test Account', 'account_type' => 'Bank',
            'chart_of_account_id' => $cash->id, 'opening_balance' => 100, 'current_balance' => 100,
        ]);
        $taxType = TaxType::where('code', 'WTAX-COMP')->firstOrFail();

        $response = $this->actingAs($accountant)->postJson('/api/tax/remittances', [
            'tax_type_id' => $taxType->id,
            'period_covered' => 'September 2026',
            'remittance_date' => now()->toDateString(),
            'amount' => 5000,
            'bank_account_id' => $bankAccount->id,
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('bank_accounts', ['id' => $bankAccount->id, 'current_balance' => 100]);
        $this->assertDatabaseCount('tax_remittances', 0);
    }
}