<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $roleSlug = 'administrator', string $status = 'Active'): User
    {
        $role = Role::firstOrCreate(['slug' => $roleSlug], ['name' => ucfirst($roleSlug)]);
        $department = Department::firstOrCreate(['name' => 'Finance Office']);

        return User::create([
            'name' => 'Test User',
            'email' => 'test.user@example.test',
            'password' => Hash::make('CorrectHorse1!'),
            'role_id' => $role->id,
            'department_id' => $department->id,
            'status' => $status,
        ]);
    }

    public function test_valid_login_succeeds_and_regenerates_session(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'CorrectHorse1!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_shows_generic_error(): void
    {
        $user = $this->makeUser();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'WrongPassword!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Invalid email or password',
            session('errors')->first('email')
        );
        $this->assertGuest();
    }

    public function test_disabled_account_cannot_authenticate(): void
    {
        $user = $this->makeUser(status: 'Suspended');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'CorrectHorse1!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    }

    public function test_logout_invalidates_the_session(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
