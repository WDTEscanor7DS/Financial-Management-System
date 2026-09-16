<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ApprovalWorkflowSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(ApprovalWorkflowSeeder::class);
    }

    private function userWithRole(string $slug): User
    {
        return User::factory()->create([
            'role_id' => Role::where('slug', $slug)->value('id'),
            'status' => 'Active',
        ]);
    }

    public function test_creating_request_creates_pending_actions_for_all_steps(): void
    {
        $accountant = $this->userWithRole('accountant');

        $response = $this->actingAs($accountant)->postJson('/api/approvals', [
            'workflow_code' => 'purchase_order',
            'description' => 'Test purchase request',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('approval_actions', 2);
        $this->assertDatabaseHas('approval_requests', ['status' => 'Pending', 'current_sequence' => 1]);
    }

    public function test_wrong_role_cannot_act_on_current_step(): void
    {
        $accountant = $this->userWithRole('accountant');
        $create = $this->actingAs($accountant)->postJson('/api/approvals', [
            'workflow_code' => 'purchase_order',
            'description' => 'Test purchase request',
        ]);
        $requestId = $create->json('data.raw_id');

        // Accountant tries to act on step 1, which requires College Administrator.
        $response = $this->actingAs($accountant)->postJson("/api/approvals/{$requestId}/act", [
            'decision' => 'Approved',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('approval_requests', ['id' => $requestId, 'status' => 'Pending', 'current_sequence' => 1]);
    }

    public function test_full_two_step_approval_completes_the_request(): void
    {
        $accountant = $this->userWithRole('accountant');
        $collegeAdmin = $this->userWithRole('college-administrator');

        $create = $this->actingAs($accountant)->postJson('/api/approvals', [
            'workflow_code' => 'purchase_order',
            'description' => 'Test purchase request',
        ]);
        $requestId = $create->json('data.raw_id');

        $step1 = $this->actingAs($collegeAdmin)->postJson("/api/approvals/{$requestId}/act", [
            'decision' => 'Approved',
        ]);
        $step1->assertStatus(200);
        $this->assertDatabaseHas('approval_requests', ['id' => $requestId, 'status' => 'Pending', 'current_sequence' => 2]);

        $step2 = $this->actingAs($accountant)->postJson("/api/approvals/{$requestId}/act", [
            'decision' => 'Approved',
        ]);
        $step2->assertStatus(200);
        $this->assertDatabaseHas('approval_requests', ['id' => $requestId, 'status' => 'Approved']);
    }

    public function test_rejection_at_first_step_finalizes_as_rejected(): void
    {
        $accountant = $this->userWithRole('accountant');
        $collegeAdmin = $this->userWithRole('college-administrator');

        $create = $this->actingAs($accountant)->postJson('/api/approvals', [
            'workflow_code' => 'purchase_order',
            'description' => 'Test purchase request',
        ]);
        $requestId = $create->json('data.raw_id');

        $reject = $this->actingAs($collegeAdmin)->postJson("/api/approvals/{$requestId}/act", [
            'decision' => 'Rejected',
            'remarks' => 'Not within budget',
        ]);

        $reject->assertStatus(200);
        $this->assertDatabaseHas('approval_requests', ['id' => $requestId, 'status' => 'Rejected']);

        // Accountant should no longer be able to act since the request is finalized.
        $secondAttempt = $this->actingAs($accountant)->postJson("/api/approvals/{$requestId}/act", [
            'decision' => 'Approved',
        ]);
        $secondAttempt->assertStatus(422);
    }
}