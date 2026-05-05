<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('synonym_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->index();

            // الكلمتان المقترحتان كمرادفتين
            $table->string('word_a', 100);
            $table->string('word_b', 100);
            $table->string('language', 10)->default('en');

            // مقاييس الـ similarity
            $table->decimal('jaccard_score', 8, 6)->default(0); // 0.0 → 1.0
            $table->decimal('cooccurrence_count', 10, 0)->default(0); // عدد مرات الظهور المشترك
            $table->decimal('confidence_score', 8, 4)->default(0); // الـ score النهائي

            // إحصائيات إضافية
            $table->unsignedInteger('word_a_count')->default(0); // عدد مرات ظهور word_a
            $table->unsignedInteger('word_b_count')->default(0); // عدد مرات ظهور word_b

            // حالة المراجعة
            $table->enum('status', [
                'pending',   // بانتظار المراجعة
                'approved',  // تمت الموافقة → ستُضاف للـ SynonymProvider
                'rejected',  // رُفض
                'merged',    // دُمج مع مجموعة موجودة
            ])->default('pending')->index();

            $table->text('reviewer_notes')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('last_computed_at')->nullable();

            $table->timestamps();

            // منع التكرار (word_a, word_b بالترتيب الأبجدي دائماً)
            $table->unique(
                ['project_id', 'word_a', 'word_b', 'language'],
                'ss_project_words_lang_unique'
            );

            $table->index(['project_id', 'language', 'confidence_score'], 'ss_confidence_idx');
            $table->index(['project_id', 'status'], 'ss_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('synonym_suggestions');
    }
};
