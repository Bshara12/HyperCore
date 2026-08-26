<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the platform-operator account and gives it the hyper_core role.
 *
 * Kept separate from UserRoleSeeder on purpose: that seeder explicitly excludes
 * hyper_core from its 50 fake users, and it should keep doing so. This one
 * seeds exactly ONE named operator instead of handing the role out by rotation.
 *
 * hyper_core is the platform superuser — it reads every tenant's projects and
 * can rotate signing keys and delete users. Override the credentials per
 * environment via HYPERCORE_SEED_EMAIL / HYPERCORE_SEED_PASSWORD, and do not
 * ship the defaults anywhere public.
 */
class HyperCoreUserSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        $email = env('HYPERCORE_SEED_EMAIL', 'hypercore@hypercore.local');
        $password = env('HYPERCORE_SEED_PASSWORD', 'password123');
        $name = env('HYPERCORE_SEED_NAME', 'HyperCore Operator');

        $roleId = DB::table('roles')
            ->where('name', 'hyper_core')
            ->whereNull('project_id')
            ->value('id');

        if (! $roleId) {
            $this->command?->error(
                'hyper_core role is missing — run RolesAndPermissionsSeeder first.'
            );

            return;
        }

        // Idempotent: re-running the seeder must not create a second operator
        // or reset a password that was changed after the first run.
        $existingId = DB::table('users')
            ->where('email', $email)
            ->value('id');

        if ($existingId) {
            $userId = $existingId;

            DB::table('users')
                ->where('id', $userId)
                ->update([
                    'is_verified' => true,
                    'updated_at' => $now,
                ]);
        } else {
            $userId = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_verified' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $alreadyAssigned = DB::table('role_user')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->exists();

        if (! $alreadyAssigned) {
            DB::table('role_user')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                // Platform-wide role: no project scope.
                'project_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->command?->info("hyper_core operator ready: {$email}");

        if ($existingId) {
            $this->command?->warn(
                'Account already existed — its password was left untouched.'
            );
        }
    }
}
