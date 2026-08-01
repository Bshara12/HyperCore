<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |----------------------------------------------------------------
        | 1) roles: unique('name') → unique(['name', 'project_id'])
        |    بدون هذا التصحيح، مشروعين مختلفين ما فيهم يسموا دور
        |    بنفس الاسم حتى لو كل واحد ضمن نطاقه الخاص (project_id مختلف)
        |----------------------------------------------------------------
        */
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name']); // يحذف roles_name_unique (اسم تلقائي حسب اتفاقية Laravel)
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['name', 'project_id'], 'roles_name_project_unique');
        });

        /*
        |----------------------------------------------------------------
        | 2) permessions: نفس المشكلة بالضبط، نفس الحل
        |----------------------------------------------------------------
        */
        Schema::table('permessions', function (Blueprint $table) {
            $table->dropUnique(['name']); // يحذف permessions_name_unique
        });

        Schema::table('permessions', function (Blueprint $table) {
            $table->unique(['name', 'project_id'], 'permessions_name_project_unique');
        });

        /*
        |----------------------------------------------------------------
        | 3) role_user: الأخطر — Primary Key (user_id, role_id) كان يمنع
        |    نفس المستخدم من امتلاك نفس الدور بأكتر من مشروع واحد
        |    (بما إن project_id ما كان جزء من الـ Primary Key الأصلي)
        |----------------------------------------------------------------
        */
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropPrimary(); // بدون آرغيومنت: يحذف الـ Primary Key الحالي بغض النظر عن أعمدته
        });

        Schema::table('role_user', function (Blueprint $table) {
            /*
             | القيد الجديد: نفس (user_id, role_id) مسموح يتكرر
             | بس بشرط project_id مختلف في كل مرة
             | (مستخدم واحد ممكن يكون له نفس الدور بأكتر من مشروع مستقل عن بعض)
             */
            $table->unique(['user_id', 'role_id', 'project_id'], 'role_user_user_role_project_unique');
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropUnique('role_user_user_role_project_unique');
        });

        Schema::table('role_user', function (Blueprint $table) {
            $table->primary(['user_id', 'role_id']);
        });

        Schema::table('permessions', function (Blueprint $table) {
            $table->dropUnique('permessions_name_project_unique');
        });

        Schema::table('permessions', function (Blueprint $table) {
            $table->unique('name');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_project_unique');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unique('name');
        });
    }
};
