<?php

namespace Database\Seeders;

use App\Models\ApprovalWorkflow;
use Illuminate\Database\Seeder;

class ApprovalWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $workflow = ApprovalWorkflow::updateOrCreate(
            ['code' => 'purchase_order'],
            ['name' => 'Purchase Order Approval', 'is_active' => true]
        );

        $steps = [
            ['sequence' => 1, 'step_name' => 'College Administrator Review', 'required_permission' => 'approve_po_college_admin'],
            ['sequence' => 2, 'step_name' => 'Accountant Final Approval', 'required_permission' => 'approve_po_finance'],
        ];

        foreach ($steps as $step) {
            $workflow->steps()->updateOrCreate(
                ['sequence' => $step['sequence']],
                $step
            );
        }
    }
}