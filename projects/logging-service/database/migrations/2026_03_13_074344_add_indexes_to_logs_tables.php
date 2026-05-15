<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('logs', function ($table) {
            $table->index('module');
            $table->index('event_type');
            $table->index('user_id');
            $table->index('occurred_at');
            $table->index(['module', 'event_type']);
        });

        Schema::table('audit_logs', function ($table) {
            $table->index('module');
            $table->index('entity_type');
            $table->index('entity_id');
            $table->index('occurred_at');
        });
    }

    public function down()
    {
        Schema::table('logs', function ($table) {
            $table->dropIndex(['module']);
            $table->dropIndex(['event_type']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['occurred_at']);
            $table->dropIndex(['module', 'event_type']);
        });

        Schema::table('audit_logs', function ($table) {
            $table->dropIndex(['module']);
            $table->dropIndex(['entity_type']);
            $table->dropIndex(['entity_id']);
            $table->dropIndex(['occurred_at']);
        });
    }
};
