<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();
        $numberOfUsers = 50; // عدد المستخدمين الوهميين

        // الحصول على الـ Roles
        $roles = DB::table('roles')->pluck('id', 'name')->toArray();

        // قائمة أسماء الأدوار لتوزيع عشوائي
        $roleNames = array_keys($roles);

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

            $userId = DB::table('users')->where('email', $email)->value('id');

            // توزيع دور عشوائي
            $assignedRoleName = $roleNames[array_rand($roleNames)];
            $roleId = $roles[$assignedRoleName];

            // ربط المستخدم بالدور في pivot بدون تكرار
            DB::table('role_user')->updateOrInsert(
                [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                ],
                [
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }
    }
}
