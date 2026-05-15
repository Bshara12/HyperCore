<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // $table->foreignId('project_id')->nullable()->index();
            // $table->foreignUlid('project_id')->nullable()->index();
            $table->string('project_id')->nullable()->index();

            $table->string('recipient_type');
            $table->unsignedBigInteger('recipient_id');

            $table->string('source_type')->nullable(); // user | system | domain_event
            $table->string('source_service')->nullable(); // auth | cms | ecommerce | booking | scheduler
            $table->string('source_id')->nullable(); // event id / domain object id

            $table->string('created_by_type')->nullable(); // user | service
            $table->string('created_by_id')->nullable();

            $table->string('correlation_id')->nullable()->index();
            $table->string('causation_id')->nullable()->index();
            $table->string('request_id')->nullable()->index();

            $table->json('actor_snapshot')->nullable();
            $table->json('source_snapshot')->nullable();
            $table->json('audit_meta')->nullable();

            $table->foreignUlid('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->string('topic_key')->nullable();

            $table->string('title');
            $table->text('body')->nullable();

            $table->json('data')->nullable();
            $table->json('metadata')->nullable();

            $table->unsignedTinyInteger('priority')->default(0);

            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->string('dedupe_key')->nullable();
            $table->foreignUlid('batch_id')->nullable()->constrained('notification_batches')->nullOnDelete();

            $table->timestamps();

            $table->unique(['project_id', 'recipient_type', 'recipient_id', 'dedupe_key'], 'notifications_dedupe_unique');
            $table->index(['recipient_type', 'recipient_id', 'read_at'], 'notifications_recipient_read_idx');
            $table->index(['project_id', 'recipient_type', 'recipient_id'], 'notifications_project_recipient_idx');
            $table->index(['status', 'scheduled_at'], 'notifications_status_scheduled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
