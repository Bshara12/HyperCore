<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // $table->foreignId('project_id')->nullable()->index();
            // $table->foreignUlid('project_id')->nullable()->index();
            $table->string('project_id')->nullable()->index();

            $table->string('subscriber_type');
            $table->unsignedBigInteger('subscriber_id');

            $table->string('topic_key');
            $table->json('channel_mask')->nullable();
            $table->json('filters')->nullable();

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(
                ['project_id', 'subscriber_type', 'subscriber_id', 'topic_key'],
                'notification_subscriptions_unique'
            );
            $table->index(['subscriber_type', 'subscriber_id'], 'notification_subscriptions_subscriber_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_subscriptions');
    }
};
