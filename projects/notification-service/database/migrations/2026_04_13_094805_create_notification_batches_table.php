<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_batches', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // $table->foreignId('project_id')->nullable()->index();
            // $table->foreignUlid('project_id')->nullable()->index();
            $table->string('project_id')->nullable()->index();
            $table->string('created_by_type')->nullable();
            $table->string('created_by_id')->nullable();

            $table->string('correlation_id')->nullable()->index();
            $table->string('causation_id')->nullable()->index();
            $table->string('request_id')->nullable()->index();

            $table->json('actor_snapshot')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->json('audit_meta')->nullable();

            $table->string('source_service');
            $table->string('source_event_type')->nullable();

            $table->string('audience_type'); // topic | segment | custom
            $table->json('audience_query')->nullable();

            $table->json('payload')->nullable();

            $table->string('status')->default('draft');
            $table->string('dedupe_key')->nullable()->unique();

            $table->unsignedInteger('total_targets')->default(0);
            $table->unsignedInteger('processed_targets')->default(0);

            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();


            $table->timestamps();

            $table->index(['status', 'scheduled_at'], 'notification_batches_status_scheduled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_batches');
    }
};
