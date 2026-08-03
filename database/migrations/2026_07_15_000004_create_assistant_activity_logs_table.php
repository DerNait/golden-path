<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for the AI assistant API: records sensitive activity (at least
 * every recommendation-draft write) with the token that performed it. No
 * secrets are stored, only identifiers and request metadata.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_activity_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('token_id')->nullable();
            $table->string('token_name')->nullable();
            $table->string('ability')->nullable();
            $table->string('method', 10);
            $table->string('path');
            $table->string('subject')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_activity_logs');
    }
};
