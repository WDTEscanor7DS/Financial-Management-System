<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class, // roles + permissions first
            DepartmentSeeder::class,     // users reference departments
            UserSeeder::class,           // depends on both of the above
        ]);
    }
}
