<?php

// database/migrations/..._add_project_id_to_permessions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permessions', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('permessions', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });
    }
};
