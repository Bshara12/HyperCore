<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'subscription_access_rules',
            function (Blueprint $table) {

                $table->id();

                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained()
                    ->cascadeOnDelete();

                $table->string('event_key');

                $table->boolean(
                    'requires_subscription'
                )->default(false);

                $table->string(
                    'required_feature_key'
                )->nullable();

                $table->boolean(
                    'is_active'
                )->default(true);

                $table->json('metadata')
                    ->nullable();

                $table->timestamps();

                $table->unique([
                    'project_id',
                    'event_key'
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'subscription_access_rules'
        );
    }
};