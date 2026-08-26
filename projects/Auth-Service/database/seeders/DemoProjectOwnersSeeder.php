<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * The demo project owners, as real Auth accounts.
 *
 * Identity lives in this service — `projects.owner_id` in the CMS is an Auth
 * user id (see CreateProjectDTO::fromRequest, which reads auth_user['id']).
 * The CMS seeders used to invent their own rows in the CMS `users` table and
 * put those ids in owner_id, which meant:
 *
 *   - none of the seeded owners could log in at all, because the dashboard
 *     authenticates against this service and these emails did not exist here
 *   - the ids landed in owner_id from a different sequence entirely, so they
 *     matched real Auth users only by coincidence — CMS user 54 and Auth
 *     user 54 were two unrelated people
 *
 * These accounts are the ones the CMS seeders now resolve by email, so a
 * seeded project genuinely belongs to an account you can sign in as.
 *
 * Emails and the shared password are overridable, but the defaults are
 * intentionally obvious: this is development data.
 */
class DemoProjectOwnersSeeder extends Seeder
{
    /**
     * Keep in step with the CMS seeders that resolve these addresses.
     *
     * @return array<int, array{email: string, name: string}>
     */
    public static function owners(): array
    {
        return [
            [
                'email' => 'clinic-owner@hypercore.test',
                'name' => 'Dr. Nour Haddad',
            ],
            [
                'email' => 'pulse360-owner@hypercore.test',
                'name' => 'Marcus Lindqvist',
            ],
            [
                'email' => 'shop-owner@hypercore.test',
                'name' => 'Leila Barakat',
            ],
            [
                'email' => 'analytics-owner@hypercore.test',
                'name' => 'Tobias Vance',
            ],
        ];
    }

    public function run(): void
    {
        $now = Carbon::now();
        $password = env('DEMO_SEED_PASSWORD', 'password123');

        $roleId = DB::table('roles')
            ->where('name', 'owner')
            ->whereNull('project_id')
            ->value('id');

        if (! $roleId) {
            $this->command?->error(
                'owner role is missing — run RolesAndPermissionsSeeder first.'
            );

            return;
        }

        $rows = [];

        foreach (self::owners() as $owner) {

            // Idempotent, and never resets a password that was changed after
            // the first run — same contract as HyperCoreUserSeeder.
            $userId = DB::table('users')
                ->where('email', $owner['email'])
                ->value('id');

            $existed = $userId !== null;

            if ($existed) {
                DB::table('users')
                    ->where('id', $userId)
                    ->update([
                        'is_verified' => true,
                        'updated_at' => $now,
                    ]);
            } else {
                $userId = DB::table('users')->insertGetId([
                    'name' => $owner['name'],
                    'email' => $owner['email'],
                    'password' => Hash::make($password),
                    // Verified, otherwise login stops at the OTP step.
                    'is_verified' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $hasRole = DB::table('role_user')
                ->where('user_id', $userId)
                ->where('role_id', $roleId)
                ->exists();

            if (! $hasRole) {
                DB::table('role_user')->insert([
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    // Platform-wide `owner` role. Per-project grants are
                    // written by the project join flow, not here.
                    'project_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $rows[] = [
                $userId,
                $owner['email'],
                $existed ? 'existing' : 'created',
                $existed ? '(unchanged)' : $password,
            ];
        }

        $this->command?->info('Demo project owners ready.');
        $this->command?->table(
            ['Auth user id', 'Email', 'Account', 'Password'],
            $rows
        );
    }
}
