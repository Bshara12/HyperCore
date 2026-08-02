<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('my_sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();

            // relations
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // device info
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('device_name')->nullable();

            // activity
            $table->dateTime('last_activity_at')->nullable();

            // expiry & revoke
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();

            // timestamps
            $table->timestamps();

            // indexes
            $table->index('expires_at');
            $table->index('revoked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('my_sessions');
    }
};
