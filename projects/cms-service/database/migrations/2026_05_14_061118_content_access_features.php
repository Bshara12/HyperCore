<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Strategy:
     * 1. Create content_access_features table
     * 2. Migrate existing required_feature data → new table (safe, no data loss)
     * 3. Drop required_feature column from content_access_metadata
     */
    public function up(): void
    {
        // ─── Step 1: Create the new pivot table ──────────────────────
        Schema::create(
            'content_access_features',
            function (Blueprint $table) {

                $table->id();

                $table->foreignId('content_access_metadata_id')
                    ->constrained('content_access_metadata')
                    ->cascadeOnDelete();

                $table->string('feature_key');

                $table->timestamps();

                // Prevent duplicate feature per content rule
                $table->unique(
                    ['content_access_metadata_id', 'feature_key'],
                    'caf_metadata_feature_unique'
                );

                // Fast lookup by feature_key
                $table->index(
                    'feature_key',
                    'caf_feature_key_idx'
                );
            }
        );

        // ─── Step 2: Migrate existing data (safe zero-downtime) ──────
        // Any existing required_feature values become the first allowed feature
        DB::statement("
            INSERT INTO content_access_features
                (content_access_metadata_id, feature_key, created_at, updated_at)
            SELECT
                id,
                required_feature,
                NOW(),
                NOW()
            FROM content_access_metadata
            WHERE required_feature IS NOT NULL
              AND required_feature != ''
        ");

        // ─── Step 3: Drop the old column ─────────────────────────────
        Schema::table(
            'content_access_metadata',
            function (Blueprint $table) {
                $table->dropColumn('required_feature');
            }
        );
    }

    public function down(): void
    {
        // Re-add the column
        Schema::table(
            'content_access_metadata',
            function (Blueprint $table) {
                $table->string('required_feature')
                    ->nullable()
                    ->after('requires_subscription');
            }
        );

        // Restore first feature back (best effort, data was many-to-one)
        DB::statement("
            UPDATE content_access_metadata cam
            JOIN (
                SELECT
                    content_access_metadata_id,
                    MIN(feature_key) AS feature_key
                FROM content_access_features
                GROUP BY content_access_metadata_id
            ) caf ON caf.content_access_metadata_id = cam.id
            SET cam.required_feature = caf.feature_key
        ");

        Schema::dropIfExists('content_access_features');
    }
};