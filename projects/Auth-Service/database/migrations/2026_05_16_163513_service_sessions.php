<?php

use App\Models\ServiceClient;
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
        Schema::create('service_sessions', function (Blueprint $table) {
            $table->char('id', 26)->primary();

            // relations
            $table->foreignIdFor(ServiceClient::class, 'service_client_id')->constrained()->onDelete('cascade');

            $table->string('client_id')->nullable();

            // activity
            $table->dateTime('last_activity_at')->nullable();

            // expiry & revoke
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();


            // timestamps
            $table->timestamps();

            // indexes
            $table->index('service_client_id');
            $table->index('expires_at');
            $table->index('revoked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_sessions');
    }
};
