<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The demo accounts every other demo seeder builds on.
 *
 *   user1@example.com   admin  — owns the "admin" group of projects
 *   owner1@example.com  owner  — owns the comprehensive project
 *   customer1..3        user   — the people who rate, order and book
 *
 * Run: php artisan db:seed --class="Database\Seeders\Demo\DemoAccountsSeeder"
 *
 * Requires RolesAndPermissionsSeeder to have run first (the roles must exist).
 * Re-running is safe: every write is keyed by email or by the fixed id.
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $roles = DB::table('roles')->pluck('id', 'name');

        if ($roles->isEmpty()) {
            $this->command?->error('Roles are missing. Run RolesAndPermissionsSeeder first.');

            return;
        }

        $accounts = [
            [
                'id' => DemoIds::ADMIN_USER_ID,
                'name' => 'Admin User',
                'email' => DemoIds::ADMIN_EMAIL,
                'role' => 'admin',
            ],
            [
                'id' => DemoIds::OWNER_USER_ID,
                'name' => 'Project Owner',
                'email' => DemoIds::OWNER_EMAIL,
                'role' => 'owner',
            ],
            [
                'id' => DemoIds::CUSTOMER_ONE_ID,
                'name' => 'Sara Haddad',
                'email' => 'customer1@example.com',
                'role' => 'user',
            ],
            [
                'id' => DemoIds::CUSTOMER_TWO_ID,
                'name' => 'Omar Nasser',
                'email' => 'customer2@example.com',
                'role' => 'user',
            ],
            [
                'id' => DemoIds::CUSTOMER_THREE_ID,
                'name' => 'Lina Khoury',
                'email' => 'customer3@example.com',
                'role' => 'user',
            ],
        ];

        DB::transaction(function () use ($accounts, $roles) {
            $now = now();

            foreach ($accounts as $account) {
                /*
                 | Match on email, not id: UserRoleSeeder already creates
                 | user1@example.com with an auto-increment id, and email is the
                 | unique column. Delete-then-insert pins the row to the fixed id
                 | the other services reference.
                 */
                DB::table('users')->where('email', $account['email'])->delete();
                DB::table('users')->where('id', $account['id'])->delete();

                DB::table('users')->insert([
                    'id' => $account['id'],
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'password' => Hash::make(DemoIds::DEMO_PASSWORD),
                    'is_verified' => true,
                    'failed_attempts' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $roleId = $roles[$account['role']] ?? null;

                if (! $roleId) {
                    $this->command?->warn("Role '{$account['role']}' not found — {$account['email']} left without a role.");

                    continue;
                }

                DB::table('role_user')->where('user_id', $account['id'])->delete();

                DB::table('role_user')->insert([
                    'user_id' => $account['id'],
                    'role_id' => $roleId,
                    'project_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });

        $this->command?->info('Demo accounts ready (password: '.DemoIds::DEMO_PASSWORD.'):');
        $this->command?->table(
            ['id', 'email', 'role'],
            array_map(fn ($a) => [$a['id'], $a['email'], $a['role']], $accounts)
        );
    }
}
