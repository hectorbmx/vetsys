<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        $this->call(RoleSeeder::class);

        $superAdminRole = Role::where([
            'name' => 'super-admin',
            'guard_name' => 'web',
        ])->firstOrFail();

        $admin = User::firstOrCreate(
            ['email' => 'hectorbmx@gmail.com'],
            [
                'name' => 'Admin General',
                'password' => Hash::make('Qwerty123.'),
                'is_active' => true,
            ]
        );

        $admin->forceFill([
            'name' => 'Admin General',
            'password' => Hash::make('Qwerty123.'),
            'is_active' => true,
        ])->save();

        $admin->assignRole($superAdminRole);
    }
}
