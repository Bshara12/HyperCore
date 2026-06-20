<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
  protected static ?string $password;

  /**
   * تعريف الحقول الافتراضية بناءً على الـ Migration الخاص بك
   */
  public function definition(): array
  {
    return [
      'name'            => fake()->name(),
      'email'           => fake()->unique()->safeEmail(),
      'password'        => static::$password ??= Hash::make('password'),
      'is_verified'     => false,
      'otp_code'        => null,
      'otp_expires_at'  => null,
      'failed_attempts' => 0,
      'locked_until'    => null,
    ];
  }

  /**
   * State خاص لرفع صلاحية المستخدم إلى Admin وتوليد الدور له في قاعدة البيانات
   */
  /**
   * State خاص لرفع صلاحية المستخدم إلى Admin
   */
  public function admin(): static
  {
    return $this->afterCreating(function (User $user) {
      // 1. بناء الأدوار بترتيب ثابت لضمان تطابق الـ IDs (سواء 1 أو 2) والحارس (api أو web)
      $superAdminId = DB::table('roles')->where('name', 'super_admin')->value('id')
        ?? DB::table('roles')->insertGetId([
          'name'       => 'super_admin',
          'guard_name' => 'web', // جرب 'web' أو 'api' حسب إعدادات تطبيقك
          'created_at' => now(),
          'updated_at' => now(),
        ]);

      $adminId = DB::table('roles')->where('name', 'admin')->value('id')
        ?? DB::table('roles')->insertGetId([
          'name'       => 'admin',
          'guard_name' => 'web',
          'created_at' => now(),
          'updated_at' => now(),
        ]);

      // 2. ربط المستخدم الحالي بدور الـ admin في جدول الـ Pivot
      DB::table('role_user')->updateOrInsert([
        'user_id' => $user->id,
        'role_id' => $adminId,
      ], ['created_at' => now(), 'updated_at' => now()]);
    });
  }

  /**
   * State خاص لرفع صلاحية المستخدم إلى Super Admin
   */
  public function superAdmin(): static
  {
    return $this->afterCreating(function (User $user) {
      // نضع الأدوار المطلوبة مباشرة مع المعرفات التي يتوقعها الكود
      // نقوم بعمل updateOrInsert لضمان عدم حدوث خطأ إذا كان الدور موجوداً مسبقاً

      // 1. ضمان وجود Super Admin (ID 2 كما يتوقع الموديل)
      DB::table('roles')->updateOrInsert(
        ['id' => 2],
        ['name' => 'super_admin', 'guard_name' => 'web', 'created_at' => now()]
      );

      // 2. ربط المستخدم
      DB::table('role_user')->updateOrInsert([
        'user_id' => $user->id,
        'role_id' => 2,
      ], [
        'created_at' => now(),
        'updated_at' => now()
      ]);
    });
  }
}
