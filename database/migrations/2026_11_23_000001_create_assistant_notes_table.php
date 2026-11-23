<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_session_id')->nullable()->constrained()->nullOnDelete();
            // note: free comment · summary: day wrap-up · change: routine edit made by the assistant
            $table->string('kind', 16)->default('note');
            $table->date('for_date');
            $table->text('body');
            $table->json('payload_json')->nullable();
            $table->string('token_name')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'for_date']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_notes');
    }
};
