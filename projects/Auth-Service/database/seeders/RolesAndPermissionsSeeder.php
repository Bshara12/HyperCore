<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        /*
        |--------------------------------------------------------------------------
        | 1) إنشاء الصلاحيات أو تحديثها
        |--------------------------------------------------------------------------
        */

        $permissions = [
            'read.data',
            'update.data',
            'delete.data',
            'show.user',
            'delete.user',
            'update.user',
        ];

        $permissionIds = [];

        foreach ($permissions as $permission) {

            DB::table('permessions')->updateOrInsert(
                ['name' => $permission],
                [
                    'guard_name' => 'api',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $permissionIds[$permission] = DB::table('permessions')
                ->where('name', $permission)
                ->value('id');
        }

        /*
        |--------------------------------------------------------------------------
        | 2) إنشاء الأدوار أو تحديثها
        |--------------------------------------------------------------------------
        */

        $roles = [
            'owner',
            'super_admin',
            'admin',
            'user',
        ];

        $roleIds = [];

        foreach ($roles as $role) {

            DB::table('roles')->updateOrInsert(
                ['name' => $role],
                [
                    'guard_name' => 'api',
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $roleIds[$role] = DB::table('roles')
                ->where('name', $role)
                ->value('id');
        }

        /*
        |--------------------------------------------------------------------------
        | 3) توزيع الصلاحيات بدون تكرار
        |--------------------------------------------------------------------------
        */

        $this->syncPermissions($roleIds['owner'], [
            $permissionIds['read.data'],
            $permissionIds['update.data'],
            $permissionIds['delete.data'],
        ]);

        $this->syncPermissions($roleIds['admin'], [
            $permissionIds['read.data'],
            $permissionIds['update.data'],
            $permissionIds['delete.data'],
        ]);

        // super_admin → جميع الصلاحيات
        $this->syncPermissions(
            $roleIds['super_admin'],
            array_values($permissionIds)
        );

        // user → read فقط
        $this->syncPermissions($roleIds['user'], [
            $permissionIds['read.data'],
        ]);
    }

    private function syncPermissions(int $roleId, array $permissionIds): void
    {
        foreach ($permissionIds as $permissionId) {

            DB::table('permession_role')->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'permession_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now()
                ],
                []
            );
        }
    }
}

