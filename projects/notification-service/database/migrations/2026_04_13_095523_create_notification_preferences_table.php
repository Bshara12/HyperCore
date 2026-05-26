<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('project_id', 50)->nullable()->index();

            $table->string('recipient_type', 50);

            $table->unsignedBigInteger('recipient_id');

            $table->string('topic_key', 100)->nullable();

            $table->string('channel', 30); // database | broadcast | email | sms | webhook

            $table->boolean('enabled')->default(true);

            $table->timestamp('mute_until')->nullable();

            $table->json('quiet_hours')->nullable();

            $table->string('delivery_mode', 30)->nullable(); // instant | digest | muted

            $table->string('locale', 10)->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique(
                ['project_id', 'recipient_type', 'recipient_id', 'topic_key', 'channel'],
                'notification_preferences_unique'
            );

            $table->index(
                ['recipient_type', 'recipient_id'],
                'notification_preferences_recipient_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
