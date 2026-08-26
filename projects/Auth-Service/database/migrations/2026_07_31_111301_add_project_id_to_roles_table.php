<?php

// database/migrations/..._add_project_id_to_roles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            /*
             | project_id غير مرتبط بـ Foreign Key لأن جدول المشاريع
             | يعيش فعلياً في خدمة CMS وليس في خدمة Auth (نظام Microservices)
             | null = دور عام على مستوى النظام كله
             | قيمة = دور خاص بمشروع محدد فقط
             */
            $table->unsignedBigInteger('project_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });
    }
};
