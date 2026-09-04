<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['1000', 'Cash', 'Asset', 'Debit'],
            ['1100', 'Accounts Receivable', 'Asset', 'Debit'],
            ['1200', 'Inventory', 'Asset', 'Debit'],
            ['1500', 'Equipment / Assets', 'Asset', 'Debit'],
            ['2000', 'Accounts Payable', 'Liability', 'Credit'],
            ['2100', 'Taxes Payable', 'Liability', 'Credit'],
            ['3000', 'Fund Balance / Equity', 'Equity', 'Credit'],
            ['4000', 'Revenue', 'Revenue', 'Credit'],
            ['5000', 'Operating Expense', 'Expense', 'Debit'],
            ['5100', 'Payroll Expense', 'Expense', 'Debit'],
            ['5200', 'Tax Expense', 'Expense', 'Debit'],
            ['5300', 'Depreciation Expense', 'Expense', 'Debit'],
        ];

        foreach ($accounts as [$code, $name, $type, $normal]) {
            ChartOfAccount::updateOrCreate(
                ['account_code' => $code],
                ['account_name' => $name, 'account_type' => $type, 'normal_balance' => $normal, 'is_active' => true]
            );
        }
    }
}