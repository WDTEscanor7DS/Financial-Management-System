<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Section 68: development/testing accounts only. The email domain
 * (example.test) is IANA-reserved for documentation/testing and will never
 * resolve, so these can never be mistaken for real Bestlink addresses.
 *
 * The shared demo password is read from DEMO_USER_PASSWORD in .env (see
 * .env.example) rather than typed directly into this file, so nobody
 * copy-pastes a hardcoded password out of source control into a real
 * deployment. If that variable is not set, a random one is generated and
 * printed to the console output of `php artisan db:seed` -- it is not
 * silently defaulted to something guessable.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('DEMO_USER_PASSWORD');

        if (! $password) {
            $password = str()->password(14);
            $this->command?->warn(
                "DEMO_USER_PASSWORD not set in .env -- generated a random demo password: {$password}"
            );
        }

        $financeOffice = Department::where('name', 'Finance Office')->firstOrFail();
        $registrar = Department::where('name', 'Registrar')->firstOrFail();

        $accounts = [
            ['name' => 'Rafael M. Osorio', 'email' => 'admin@example.test', 'role' => 'administrator', 'department_id' => $financeOffice->id],
            ['name' => 'Dianne C. Torralba', 'email' => 'accountant@example.test', 'role' => 'accountant', 'department_id' => $financeOffice->id],
            ['name' => 'Dr. Emilio S. Ferrer', 'email' => 'collegeadmin@example.test', 'role' => 'college-administrator', 'department_id' => null],
            ['name' => 'Kristine Joy A. Panganiban', 'email' => 'employee@example.test', 'role' => 'employee', 'department_id' => $registrar->id],
        ];

        foreach ($accounts as $account) {
            $role = Role::where('slug', $account['role'])->firstOrFail();

            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'department_id' => $account['department_id'],
                    'role_id' => $role->id,
                    'password' => Hash::make($password),
                    'status' => 'Active',
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
