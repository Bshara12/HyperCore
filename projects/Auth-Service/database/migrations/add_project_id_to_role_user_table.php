<?php
// database/migrations/..._add_project_id_to_role_user_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            /*
             | project_id هنا يحدد "سياق" هذا الإسناد بالذات
             | null = هذا دور المستخدم العام في كل النظام
             | قيمة = هذا دور المستخدم داخل هذا المشروع تحديداً فقط
             |
             | ملاحظة: لا نضع unique constraint على (user_id, project_id) لأن
             | MySQL يتعامل مع كل NULL كقيمة مختلفة عن الأخرى في الـ unique index،
             | لذا التحقق من عدم التكرار سيتم يدوياً داخل الكود (Repository)
             */
            $table->unsignedBigInteger('project_id')->nullable()->after('role_id')->index();
            $table->index(['user_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });
    }
};
