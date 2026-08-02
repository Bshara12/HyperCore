<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();
        $numberOfUsers = 50;

        // جلب الأدوار بترتيب ثابت حتى يكون التوزيع ثابتًا
        // ✅ استثناء hyper_core: دور حصري لمطوري النظام الحقيقيين، لا يُسند عشوائياً لمستخدمين وهميين
        $roles = DB::table('roles')
            ->where('name', '!=', 'hyper_core')
            ->orderBy('name')
            ->pluck('id', 'name')
            ->toArray();

        if (empty($roles)) {
            return;
        }

        $roleIds = array_values($roles);
        $rolesCount = count($roleIds);

        for ($i = 1; $i <= $numberOfUsers; $i++) {
            $email = "user{$i}@example.com";

            // إنشاء المستخدم أو تحديثه
            DB::table('users')->updateOrInsert(
                ['email' => $email],
                [
                    'name' => "Fake User {$i}",
                    'password' => Hash::make('password123'),
                    'is_verified' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );

            $userId = DB::table('users')
                ->where('email', $email)
                ->value('id');

            if (!$userId) {
                continue;
            }

            // توزيع ثابت: نفس المستخدم يأخذ نفس الدور دائمًا
            $roleIndex = ($i - 1) % $rolesCount;
            $roleId = $roleIds[$roleIndex];

            // حذف أي ربط سابق ثم إضافة الربط الجديد
            DB::table('role_user')
                ->where('user_id', $userId)
                ->delete();

            DB::table('role_user')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
