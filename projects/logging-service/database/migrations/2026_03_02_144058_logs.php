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
        //
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('module'); // auth, cms, ecommerce, booking
            $table->string('event_type');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable();
            $table->json('metadata')->nullable();
            $table->string('correlation_id')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
