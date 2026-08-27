<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Fund;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(DepartmentSeeder::class);
    }

    /**
     * Simulates the Section 51 scenario: a ₱100,000 fund, two allocation
     * requests of ₱80,000 each. Real concurrency (two simultaneous
     * connections both reading the same starting balance) is exercised by
     * FundService's lockForUpdate() at the database level and isn't
     * practical to reproduce from a single-threaded test process -- what
     * this test does verify is the business rule the lock protects: once
     * the first ₱80,000 allocation commits, the fund's true remaining
     * balance is ₱20,000, and a second ₱80,000 request against that same
     * fund must be rejected rather than silently succeeding and driving
     * the fund negative.
     */
    public function test_second_allocation_exceeding_remaining_balance_is_rejected(): void
    {
        $accountant = User::factory()->create([
            'role_id' => \App\Models\Role::where('slug', 'accountant')->value('id'),
            'status' => 'Active',
        ]);
        $department = Department::first();

        $fund = Fund::create([
            'name' => 'Test Fund', 'type' => 'Operating', 'department_id' => $department->id,
            'allocation' => 100000, 'used' => 0, 'status' => 'Active', 'created_by' => $accountant->id,
        ]);

        $first = $this->actingAs($accountant)->postJson("/api/funds/{$fund->id}/allocate", ['amount' => 80000]);
        $first->assertStatus(200);
        $first->assertJsonPath('data.used', 80000);
        $first->assertJsonPath('data.remaining', 20000);

        $second = $this->actingAs($accountant)->postJson("/api/funds/{$fund->id}/allocate", ['amount' => 80000]);
        $second->assertStatus(422);

        $this->assertEquals(80000, (float) $fund->fresh()->used);
    }
}
