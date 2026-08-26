<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * project_user had no unique constraint on (project_id, user_id), so
 * `insertOrIgnore` — which the join flow and the seeders both rely on to stay
 * idempotent — had nothing to detect a conflict against and simply appended a
 * second membership row.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Collapse existing duplicates first: the index cannot be created while
        // they are present.
        $duplicates = DB::table('project_user')
            ->select('project_id', 'user_id', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as copies'))
            ->groupBy('project_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('project_user')
                ->where('project_id', $duplicate->project_id)
                ->where('user_id', $duplicate->user_id)
                ->where('id', '!=', $duplicate->keep_id)
                ->delete();
        }

        Schema::table('project_user', function (Blueprint $table) {
            $table->unique(['project_id', 'user_id'], 'project_user_unique');
        });
    }

    public function down(): void
    {
        Schema::table('project_user', function (Blueprint $table) {
            $table->dropUnique('project_user_unique');
        });
    }
};
