<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_service_clients', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->string('service_name')->unique();
            $table->text('token_hash');
            $table->json('scopes')->nullable();
            $table->json('allowed_projects')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_service_clients');
    }
};
