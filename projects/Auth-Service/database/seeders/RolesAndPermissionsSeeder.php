<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $permissions = [
            'read.data',
            'update.data',
            'delete.data',
            'show.user',
            'delete.user',
            'update.user',

            /*
             | CMS data collections. The routes guard on these names, so without
             | a catalog entry every collection write answers 403 — including
             | for admins.
             |
             | Note for e-commerce: an offer IS a CMS collection, and activating
             | one reads the (currently inactive) collection back from CMS. Any
             | role that gets offer.update therefore also needs
             | cms.collection.update, or activation fails with a 404.
             */
            'cms.collection.create',
            'cms.collection.update',
            'cms.collection.delete',
        ];

        $permissionIds = [];

        foreach ($permissions as $permission) {
            DB::table('permessions')->updateOrInsert(
                ['name' => $permission],
                ['guard_name' => 'api', 'updated_at' => $now, 'created_at' => $now]
            );

            $permissionIds[$permission] = DB::table('permessions')->where('name', $permission)->value('id');
        }

        // ✅ super_admin استُبدل بـ hyper_core — دور مطوري النظام حصراً
        // لا يُسند أبداً عبر الـ Seeder، فقط عبر: php artisan hypercore:assign {email}
        $roles = [
            'owner',
            'hyper_core',
            'admin',
            'user',
        ];

        $roleIds = [];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role],
                ['guard_name' => 'api', 'updated_at' => $now, 'created_at' => $now]
            );

            $roleIds[$role] = DB::table('roles')->where('name', $role)->value('id');
        }

        $this->syncPermissions($roleIds['owner'], [
            $permissionIds['read.data'],
            $permissionIds['update.data'],
            $permissionIds['delete.data'],
            $permissionIds['cms.collection.create'],
            $permissionIds['cms.collection.update'],
            $permissionIds['cms.collection.delete'],
        ]);

        // ✅ admin أصبح يمتلك كل صلاحيات super_admin السابقة بالإضافة لصلاحياته الأصلية
        $this->syncPermissions($roleIds['admin'], array_values($permissionIds));

        // hyper_core → كل الصلاحيات أيضاً على مستوى الكتالوج
        // الفرق الحقيقي عن admin مفروض بالكود لا بهذا الجدول: حذف مستخدم/خدمة،
        // إعادة إصدار المفاتيح، وحصرية إسناد دور hyper_core نفسه
        $this->syncPermissions($roleIds['hyper_core'], array_values($permissionIds));

        $this->syncPermissions($roleIds['user'], [
            $permissionIds['read.data'],
        ]);
    }

    private function syncPermissions(int $roleId, array $permissionIds): void
    {
        foreach ($permissionIds as $permissionId) {
            // 🔴 إصلاح: كانت created_at/updated_at جوا شرط البحث بـ updateOrInsert
            // بما إن now() تتغيّر بكل استدعاء، الشرط ما كان يتطابق أبداً مع صف سابق
            // فكانت تتكرر صفوف جديدة بدل تحديث الموجود، بكل مرة يُشغَّل فيها الـ Seeder
            DB::table('permession_role')->updateOrInsert(
                ['role_id' => $roleId, 'permession_id' => $permissionId],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
