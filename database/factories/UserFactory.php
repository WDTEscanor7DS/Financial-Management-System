<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('TestPassword1!'),
            'status' => 'Active',
            'role_id' => Role::query()->inRandomOrder()->value('id'),
            'department_id' => Department::query()->inRandomOrder()->value('id'),
        ];
    }
}
