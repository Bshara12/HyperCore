<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->foreignUlid('notification_id')->constrained('notifications')->cascadeOnDelete();

            $table->string('channel'); // database | broadcast | email | sms | webhook
            $table->string('provider')->nullable();

            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(3);

            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();

            $table->string('provider_message_id')->nullable();
            $table->json('payload_snapshot')->nullable();

            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();

            $table->unique(['notification_id', 'channel'], 'notification_deliveries_unique');
            $table->index(['status', 'next_retry_at'], 'notification_deliveries_retry_idx');
            $table->index(['channel', 'status'], 'notification_deliveries_channel_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
