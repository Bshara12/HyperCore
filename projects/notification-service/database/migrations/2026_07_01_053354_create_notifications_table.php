<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {

            // ─── Primary Key ─────────────────────────────────────────────────
            // نستخدم UUID بدلاً من Auto-increment لأن الـ IDs ستُشارَك مع خدمات أخرى
            $table->uuid('id')->primary();

            // ─── User Info ───────────────────────────────────────────────────
            // معرّف المستخدم قادم من خدمة Auth (لا يوجد FK لأنه في DB مختلف)
            $table->string('user_id');

            // الإيميل نحتفظ به هنا لأننا نحتاجه عند الإرسال دون الرجوع لـ Auth
            $table->string('user_email')->nullable();

            // ─── Notification Content ─────────────────────────────────────────
            $table->string('title');
            $table->text('body');

            // بيانات إضافية مرنة: رابط، أيقونة، action type، إلخ
            $table->json('data')->nullable();

            // ─── Channel & Source ─────────────────────────────────────────────
            // القناة: email | in_app | real_time
            $table->string('channel');

            // اسم الخدمة التي طلبت الإشعار (ecommerce-service, booking-service...)
            $table->string('source_service')->nullable();

            // ─── Status Tracking ──────────────────────────────────────────────
            // الحالة: pending | sent | failed
            $table->string('status')->default('pending');

            // رسالة الخطأ في حالة فشل الإرسال
            $table->text('error_message')->nullable();

            // ─── Timestamps ───────────────────────────────────────────────────
            // وقت قراءة الإشعار (خاص بـ in_app notifications فقط)
            $table->timestamp('read_at')->nullable();

            // وقت الإرسال الفعلي
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            // ─── Indexes لتسريع الاستعلامات ──────────────────────────────────
            $table->index('user_id');
            $table->index('status');
            $table->index('channel');
            // Composite index للاستعلامات الشائعة (جلب إشعارات مستخدم من channel معين)
            $table->index(['user_id', 'channel']);
            // Composite index لاستعلامات الإشعارات غير المقروءة
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
