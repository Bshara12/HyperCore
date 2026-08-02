<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1) إعادة تسمية super_admin إلى hyper_core (في مكانها، بنفس الـ id)
        // هذا يحافظ تلقائياً على أي role_user assignments موجودة مسبقاً لهذا الدور
        $superAdmin = DB::table('roles')->whereNull('project_id')->where('name', 'super_admin')->first();

        if ($superAdmin) {
            DB::table('roles')->where('id', $superAdmin->id)->update([
                'name' => 'hyper_core',
                'updated_at' => now(),
            ]);
        }

        // 2) admin يمتص كل صلاحيات super_admin السابقة (على مستوى الكتالوج)
        $adminRole = DB::table('roles')->whereNull('project_id')->where('name', 'admin')->first();

        if ($adminRole) {
            $allPermissionIds = DB::table('permessions')->whereNull('project_id')->pluck('id');

            foreach ($allPermissionIds as $permId) {
                DB::table('permession_role')->updateOrInsert(
                    ['role_id' => $adminRole->id, 'permession_id' => $permId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        $hyperCore = DB::table('roles')->whereNull('project_id')->where('name', 'hyper_core')->first();

        if ($hyperCore) {
            DB::table('roles')->where('id', $hyperCore->id)->update([
                'name' => 'super_admin',
                'updated_at' => now(),
            ]);
        }

        // ملاحظة: لا نُزيل صلاحيات admin المُضافة عند التراجع
        // إبقاؤها كمجموعة أوسع أأمن من تخمين ما الذي كان يجب حذفه بالضبط
    }
};
