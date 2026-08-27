<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepartmentSeeder::class);
    }

    private function userWithRole(string $slug): User
    {
        $role = \App\Models\Role::where('slug', $slug)->firstOrFail();

        return User::factory()->create(['role_id' => $role->id, 'status' => 'Active']);
    }

    public function test_employee_cannot_create_revenue(): void
    {
        $employee = $this->userWithRole('employee');
        $department = Department::first();

        $response = $this->actingAs($employee)->postJson('/api/revenues', [
            'date' => now()->toDateString(),
            'revenue_type' => 'Tuition',
            'description' => 'Attempted revenue by an Employee',
            'department_id' => $department->id,
            'payer' => 'Test Payer',
            'amount' => 1000,
        ]);

        $response->assertStatus(403);
    }

    public function test_accountant_can_create_revenue(): void
    {
        $accountant = $this->userWithRole('accountant');
        $department = Department::first();

        $response = $this->actingAs($accountant)->postJson('/api/revenues', [
            'date' => now()->toDateString(),
            'revenue_type' => 'Tuition',
            'description' => 'Valid revenue by an Accountant',
            'department_id' => $department->id,
            'payer' => 'Test Payer',
            'amount' => 1000,
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.amount', 1000);
    }

    public function test_employee_cannot_view_audit_logs(): void
    {
        $employee = $this->userWithRole('employee');

        $response = $this->actingAs($employee)->getJson('/api/audit-logs');

        $response->assertStatus(403);
    }

    public function test_employee_can_submit_but_not_approve_procurement_request(): void
    {
        $employee = $this->userWithRole('employee');
        $department = Department::first();

        $store = $this->actingAs($employee)->postJson('/api/procurement', [
            'department_id' => $department->id,
            'request_type' => 'Office Supplies',
            'description' => 'Bond paper',
            'estimated_cost' => 500,
        ]);
        $store->assertStatus(201);

        $requestId = $store->json('data.raw_id');

        $review = $this->actingAs($employee)->postJson("/api/procurement/{$requestId}/review", [
            'decision' => 'approve',
        ]);
        $review->assertStatus(403);
    }
}
