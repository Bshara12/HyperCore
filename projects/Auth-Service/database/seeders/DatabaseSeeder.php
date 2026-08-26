<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        $this->call([
            RolesAndPermissionsSeeder::class,
            UserRoleSeeder::class,
            // Must run after RolesAndPermissionsSeeder: it needs the
            // hyper_core role row to exist before it can assign it.
            HyperCoreUserSeeder::class,

            // The accounts the CMS seeders resolve project ownership against.
            // Also needs the roles to exist first.
            DemoProjectOwnersSeeder::class,
        ]);

    }
}
