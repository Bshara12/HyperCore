<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 🔹 data_entries table
        Schema::table('data_entries', function (Blueprint $table) {

            // Composite index حسب استعلاماتك الحالية
            $table->index(
                ['scheduled_at', 'data_type_id'],
                'entries_sched_datatype_index'
            );
        });

        // 🔹 data_entry_values
        // لا نضيف شيء هنا لأن عندك UNIQUE مركب
    }

    public function down(): void
    {
        Schema::table('data_entries', function (Blueprint $table) {
            $table->dropIndex('entries_sched_datatype_index');
        });
    }
};
