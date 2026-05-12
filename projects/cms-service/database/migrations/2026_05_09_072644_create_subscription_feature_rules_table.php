<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    Schema::create(
      'subscription_feature_rules',
      function (Blueprint $table) {

        $table->id();

        $table->foreignId('project_id')
          ->nullable()
          ->constrained()
          ->nullOnDelete();

        /*
                Examples:
                article.create
                article.view
                ai.generate
                video.watch
                */

        $table->string('event_key')
          ->index();

        /*
                Examples:
                articles_per_month
                premium_articles
                ai_requests_daily
                */

        $table->string('feature_key')
          ->index();

        /*
                check
                increment
                both
                */

        $table->enum('action', [
          'check',
          'increment',
          'both'
        ]);

        /*
                never
                daily
                monthly
                yearly
                */

        $table->enum('reset_type', [
          'never',
          'daily',
          'monthly',
          'yearly'
        ])->default('never');

        $table->boolean('is_active')
          ->default(true)
          ->index();

        $table->json('metadata')
          ->nullable();

        $table->timestamps();

        $table->unique([
          'project_id',
          'event_key',
          'feature_key'
        ], 'subscription_feature_rules_unique');
      }
    );
  }

  public function down(): void
  {
    Schema::dropIfExists(
      'subscription_feature_rules'
    );
  }
};
