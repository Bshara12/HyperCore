<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // $table->foreignId('project_id')->nullable()->index();
            // $table->foreignUlid('project_id')->nullable()->index();
            $table->string('project_id')->nullable()->index();

            $table->string('key');
            $table->string('channel')->nullable();
            $table->string('locale')->nullable();

            $table->unsignedInteger('version')->default(1);

            $table->string('subject_template')->nullable();
            $table->text('body_template');

            $table->json('variables_schema')->nullable();
            $table->json('defaults')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['key', 'channel', 'locale', 'version'], 'notification_templates_version_unique');
            $table->index(['key', 'is_active'], 'notification_templates_key_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
