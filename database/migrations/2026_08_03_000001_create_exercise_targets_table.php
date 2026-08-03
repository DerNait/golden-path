<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Progression targets that belong to an exercise instead of a routine slot.
 *
 * The same movement can be trained in different contexts -- an Upper B
 * exercise performed as an Upper A alternative runs three sets instead of two
 * -- and each context deserves its own target, so the key is (exercise, sets).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('target_sets');
            $table->decimal('target_weight', 7, 2)->nullable();
            $table->string('weight_unit', 2)->nullable();
            $table->unsignedSmallInteger('target_total_reps')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exercise_id', 'target_sets']);
        });

        Schema::table('workout_exercises', function (Blueprint $table): void {
            // Target resolved for the exercise actually performed, frozen when
            // the session starts or the exercise is substituted.
            $table->json('target_snapshot_json')->nullable()->after('recommendation_snapshot_json');
        });
    }

    public function down(): void
    {
        Schema::table('workout_exercises', function (Blueprint $table): void {
            $table->dropColumn('target_snapshot_json');
        });

        Schema::dropIfExists('exercise_targets');
    }
};
