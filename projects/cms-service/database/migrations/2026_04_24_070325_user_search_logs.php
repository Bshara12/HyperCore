<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── جدول تسجيل عمليات البحث ─────────────────────────────────
        Schema::create('user_search_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            // nullable لدعم Guest users عبر session

            $table->unsignedBigInteger('project_id')->index();
            // كل project له سلوك مستقل

            $table->string('keyword', 200);
            $table->string('language', 10)->default('en');

            // النية المكتشفة وقت البحث
            $table->string('detected_intent', 20)->nullable();
            $table->decimal('intent_confidence', 4, 3)->nullable();

            // عدد النتائج التي أرجعها البحث
            $table->unsignedSmallInteger('results_count')->default(0);

            // session للـ guest users
            $table->string('session_id', 100)->nullable()->index();

            $table->timestamp('searched_at')->useCurrent();

            $table->index(['user_id', 'project_id']);
            $table->index(['project_id', 'searched_at']);
            $table->index(['user_id', 'project_id', 'searched_at']);
        });

        // ─── جدول تسجيل النقرات على النتائج ─────────────────────────
        Schema::create('user_click_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('project_id')->index();

            // ربط بالـ search log
            $table->foreignId('search_log_id')
                ->nullable()
                ->constrained('user_search_logs')
                ->nullOnDelete();

            // الـ entry الذي نقر عليه
            $table->unsignedBigInteger('entry_id')->index();
            $table->unsignedBigInteger('data_type_id')->index();

            // موقع النتيجة وقت النقر (position 1 = أول نتيجة)
            $table->unsignedTinyInteger('result_position')->default(0);

            $table->string('session_id', 100)->nullable()->index();

            $table->timestamp('clicked_at')->useCurrent();

            $table->index(['user_id', 'project_id']);
            $table->index(['user_id', 'project_id', 'data_type_id']);
            $table->index(['project_id', 'clicked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_click_logs');
        Schema::dropIfExists('user_search_logs');
    }
};