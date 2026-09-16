<?php

namespace Database\Seeders;

use App\Models\ChartOfAccount;
use App\Models\TaxType;
use Illuminate\Database\Seeder;

class TaxTypesSeeder extends Seeder
{
    public function run(): void
    {
        $taxTypes = [
            ['WTAX-COMP', 'Withholding Tax on Compensation', '2230', '1601-C'],
            ['VAT', 'Value-Added Tax', '2300', '2550M'],
            ['PERCENTAGE-TAX', 'Percentage Tax', '2310', '2551Q'],
        ];

        foreach ($taxTypes as [$code, $name, $accountCode, $birForm]) {
            $account = ChartOfAccount::firstOrCreate(
                ['account_code' => $accountCode],
                ['account_name' => $name . ' Payable', 'account_type' => 'Liability', 'normal_balance' => 'Credit']
            );

            TaxType::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'chart_of_account_id' => $account->id, 'bir_form_no' => $birForm, 'is_active' => true]
            );
        }
    }
}