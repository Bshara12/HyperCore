<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'subscription_access_rules',
            function (Blueprint $table) {

                $table->string('required_feature')
                    ->nullable()
                    ->after('requires_subscription')
                    ->index();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'subscription_access_rules',
            function (Blueprint $table) {

                $table->dropColumn(
                    'required_feature'
                );
            }
        );
    }
};