<?php

use App\Models\ServiceClient;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(ServiceClient::class, 'service_client_id')->constrained()->cascadeOnDelete();
            $table->string('token_id')->unique(); // jti
            $table->char('session_id', 26);
            $table->timestamp('expires_at');
            $table->boolean('revoked')->default(false);
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_refresh_tokens');
    }
};
