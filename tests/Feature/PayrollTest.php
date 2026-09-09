<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seedPayrollAccounts();
    }

    private function seedPayrollAccounts(): void
    {
        $accounts = [
            ['5100', 'Payroll Expense', 'Expense', 'Debit'],
            ['2200', 'SSS Payable', 'Liability', 'Credit'],
            ['2210', 'PhilHealth Payable', 'Liability', 'Credit'],
            ['2220', 'Pag-IBIG Payable', 'Liability', 'Credit'],
            ['2230', 'Withholding Tax Payable', 'Liability', 'Credit'],
            ['2240', 'Salaries Payable', 'Liability', 'Credit'],
        ];
        foreach ($accounts as [$code, $name, $type, $normal]) {
            ChartOfAccount::create(['account_code' => $code, 'account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal]);
        }
    }

    private function accountant(): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', 'accountant')->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_generating_payslips_computes_correct_deductions(): void
    {
        $accountant = $this->accountant();
        Employee::create([
            'employee_no' => 'EMP-0001', 'full_name' => 'Juan Dela Cruz', 'position' => 'Instructor',
            'employment_type' => 'Full-time', 'monthly_rate' => 25000, 'status' => 'Active', 'hire_date' => now(),
        ]);
        $period = PayrollPeriod::create([
            'period_label' => 'Test Period', 'start_date' => now(), 'end_date' => now()->addDays(15),
            'status' => 'Draft', 'created_by' => $accountant->id,
        ]);

        $response = $this->actingAs($accountant)->postJson("/api/payroll-periods/{$period->id}/generate");

        $response->assertStatus(200);
        $this->assertDatabaseHas('payslips', [
            'payroll_period_id' => $period->id,
            'basic_pay' => 25000,
            'sss_deduction' => 1125,     // 25000 * 4.5%
            'philhealth_deduction' => 500, // 25000 * 2%
            'pagibig_deduction' => 500,    // 25000 * 2%
            'withholding_tax' => 2500,     // 25000 * 10% (>20833 bracket)
            'net_pay' => 20375,            // 25000 - 4625
        ]);
    }

    public function test_cannot_generate_payslips_twice(): void
    {
        $accountant = $this->accountant();
        Employee::create([
            'employee_no' => 'EMP-0001', 'full_name' => 'Juan Dela Cruz', 'position' => 'Instructor',
            'employment_type' => 'Full-time', 'monthly_rate' => 25000, 'status' => 'Active', 'hire_date' => now(),
        ]);
        $period = PayrollPeriod::create([
            'period_label' => 'Test Period', 'start_date' => now(), 'end_date' => now()->addDays(15),
            'status' => 'Draft', 'created_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)->postJson("/api/payroll-periods/{$period->id}/generate")->assertStatus(200);
        $second = $this->actingAs($accountant)->postJson("/api/payroll-periods/{$period->id}/generate");

        $second->assertStatus(422);
        $this->assertDatabaseCount('payslips', 1);
    }

    public function test_posting_to_ledger_creates_balanced_consolidated_entry(): void
    {
        $accountant = $this->accountant();
        Employee::create([
            'employee_no' => 'EMP-0001', 'full_name' => 'Juan Dela Cruz', 'position' => 'Instructor',
            'employment_type' => 'Full-time', 'monthly_rate' => 25000, 'status' => 'Active', 'hire_date' => now(),
        ]);
        Employee::create([
            'employee_no' => 'EMP-0002', 'full_name' => 'Maria Santos', 'position' => 'Clerk',
            'employment_type' => 'Full-time', 'monthly_rate' => 18000, 'status' => 'Active', 'hire_date' => now(),
        ]);
        $period = PayrollPeriod::create([
            'period_label' => 'Test Period', 'start_date' => now(), 'end_date' => now()->addDays(15),
            'status' => 'Draft', 'created_by' => $accountant->id,
        ]);

        $this->actingAs($accountant)->postJson("/api/payroll-periods/{$period->id}/generate")->assertStatus(200);

        $notYetProcessed = $this->actingAs($accountant)->postJson("/api/payroll-periods/{$period->id}/post");
        // status is now Processed after generate, so posting should succeed
        $notYetProcessed->assertStatus(200);

        $this->assertDatabaseHas('payroll_periods', ['id' => $period->id, 'status' => 'Posted']);
        $this->assertDatabaseCount('journal_entries', 1);
        $this->assertDatabaseCount('journal_entry_lines', 6); // expense + 5 payables

        $entryId = $period->fresh()->journal_entry_id;
        $totalDebit = \App\Models\JournalEntryLine::where('journal_entry_id', $entryId)->sum('debit');
        $totalCredit = \App\Models\JournalEntryLine::where('journal_entry_id', $entryId)->sum('credit');
        $this->assertEquals(43000, $totalDebit);
        $this->assertEquals((float) $totalDebit, (float) $totalCredit);
    }
}