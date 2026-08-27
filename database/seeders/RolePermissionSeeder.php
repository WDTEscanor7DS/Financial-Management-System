<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The four roles and the permission slugs below are a direct translation
 * of the ROLES object that used to live in js/auth.js (modules[] +
 * canManageUsers/canApproveRequests/canEditFinancials/canSubmitRequests).
 * Nothing here is invented -- see docs/ARCHITECTURE_ASSESSMENT.md Section 2
 * for the original object this was derived from.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view_dashboard' => 'Dashboard',

            'view_budget' => 'Budget Planning', 'create_budget' => 'Budget Planning',
            'edit_budget' => 'Budget Planning', 'delete_budget' => 'Budget Planning',

            'view_revenue' => 'Revenue Management', 'create_revenue' => 'Revenue Management',
            'edit_revenue' => 'Revenue Management', 'delete_revenue' => 'Revenue Management',

            'view_expenses' => 'Expense & Disbursement', 'create_expense' => 'Expense & Disbursement',
            'edit_expense' => 'Expense & Disbursement', 'delete_expense' => 'Expense & Disbursement',

            'view_accounts_payable' => 'Accounts Payable', 'create_accounts_payable' => 'Accounts Payable',
            'record_payable_payment' => 'Accounts Payable', 'delete_accounts_payable' => 'Accounts Payable',

            'view_accounts_receivable' => 'Accounts Receivable', 'create_accounts_receivable' => 'Accounts Receivable',
            'record_receivable_payment' => 'Accounts Receivable', 'delete_accounts_receivable' => 'Accounts Receivable',

            'view_funds' => 'Fund Management', 'create_fund' => 'Fund Management',
            'allocate_funds' => 'Fund Management', 'delete_fund' => 'Fund Management',

            'view_procurement' => 'Procurement & Requests', 'create_procurement_request' => 'Procurement & Requests',
            'approve_procurement_request' => 'Procurement & Requests', 'reject_procurement_request' => 'Procurement & Requests',
            'advance_procurement_request' => 'Procurement & Requests', 'delete_procurement_request' => 'Procurement & Requests',

            'view_assets' => 'Asset & Depreciation', 'create_asset' => 'Asset & Depreciation',
            'edit_asset' => 'Asset & Depreciation', 'delete_asset' => 'Asset & Depreciation',

            'view_reports' => 'Financial Reports', 'generate_reports' => 'Financial Reports',

            'view_audit_logs' => 'Security & Audit',

            'manage_users' => 'System Administration',
            'manage_roles' => 'System Administration',
            'manage_permissions' => 'System Administration',
        ];

        foreach ($permissions as $slug => $group) {
            Permission::updateOrCreate(['slug' => $slug], [
                'label' => ucwords(str_replace('_', ' ', $slug)),
                'group' => $group,
            ]);
        }

        $allSlugs = array_keys($permissions);

        $accountantSlugs = [
            'view_dashboard',
            'view_budget', 'create_budget', 'edit_budget', 'delete_budget',
            'view_revenue', 'create_revenue', 'edit_revenue', 'delete_revenue',
            'view_expenses', 'create_expense', 'edit_expense', 'delete_expense',
            'view_accounts_payable', 'create_accounts_payable', 'record_payable_payment', 'delete_accounts_payable',
            'view_accounts_receivable', 'create_accounts_receivable', 'record_receivable_payment', 'delete_accounts_receivable',
            'view_funds', 'create_fund', 'allocate_funds', 'delete_fund',
            'view_procurement', 'create_procurement_request', 'approve_procurement_request',
            'reject_procurement_request', 'advance_procurement_request', 'delete_procurement_request',
            'view_assets', 'create_asset', 'edit_asset', 'delete_asset',
            'view_reports', 'generate_reports',
        ];

        $collegeAdminSlugs = ['view_dashboard', 'view_budget', 'view_procurement', 'view_reports', 'generate_reports'];

        $employeeSlugs = ['view_dashboard', 'view_procurement', 'create_procurement_request'];

        $roles = [
            [
                'name' => 'Administrator',
                'slug' => 'administrator',
                'description' => 'Full system oversight: users, roles, settings, and audit trail.',
                'permissions' => $allSlugs,
            ],
            [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Manages revenue, expenses, payables/receivables, budgets, and reports.',
                'permissions' => $accountantSlugs,
            ],
            [
                'name' => 'College Administrator',
                'slug' => 'college-administrator',
                'description' => 'Reviews college-wide dashboards, budgets, and financial performance.',
                'permissions' => $collegeAdminSlugs,
            ],
            [
                'name' => 'Employee',
                'slug' => 'employee',
                'description' => 'Submits procurement and financial requests, and views notifications.',
                'permissions' => $employeeSlugs,
            ],
        ];

        foreach ($roles as $roleData) {
            $role = Role::updateOrCreate(['slug' => $roleData['slug']], [
                'name' => $roleData['name'],
                'description' => $roleData['description'],
            ]);

            $permissionIds = Permission::whereIn('slug', $roleData['permissions'])->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }
}
