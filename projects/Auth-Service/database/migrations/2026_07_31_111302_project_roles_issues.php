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
        | 0) ضمان وجود عمود project_id بالجداول الثلاثة قبل أي شيء آخر
        |    (دفاعي: يعمل بغض النظر عن ترتيب تنفيذ الملفات التي قد
        |    تُضيف نفس العمود، فلا نعتمد على ترتيب أسماء الملفات)
        |----------------------------------------------------------------
        */
        if (! Schema::hasColumn('roles', 'project_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable()->after('id')->index();
            });
        }

        if (! Schema::hasColumn('permessions', 'project_id')) {
            Schema::table('permessions', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable()->after('id')->index();
            });
        }

        if (! Schema::hasColumn('role_user', 'project_id')) {
            Schema::table('role_user', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable()->after('role_id')->index();
                $table->index(['user_id', 'role_id', 'project_id']);
            });
        }

        /*
        |----------------------------------------------------------------
        | 1) roles: unique('name') → unique(['name', 'project_id'])
        |----------------------------------------------------------------
        */
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['name', 'project_id'], 'roles_name_project_unique');
        });

        /*
        |----------------------------------------------------------------
        | 2) permessions: نفس المشكلة، نفس الحل
        |----------------------------------------------------------------
        */
        Schema::table('permessions', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });

        Schema::table('permessions', function (Blueprint $table) {
            $table->unique(['name', 'project_id'], 'permessions_name_project_unique');
        });

        /*
        |----------------------------------------------------------------
        | 3) role_user: Primary Key القديم (user_id, role_id) كان يمنع
        |    نفس المستخدم من امتلاك نفس الدور بأكثر من مشروع
        |----------------------------------------------------------------
        */
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropPrimary();
        });

        Schema::table('role_user', function (Blueprint $table) {
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
