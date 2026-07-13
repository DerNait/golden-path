<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('age')->nullable(); $table->decimal('height_cm', 5, 2)->nullable();
            $table->string('biological_sex')->nullable(); $table->decimal('initial_body_weight_kg', 6, 2)->nullable();
            $table->decimal('current_body_weight_kg', 6, 2)->nullable(); $table->decimal('initial_waist_cm', 6, 2)->nullable();
            $table->decimal('current_waist_cm', 6, 2)->nullable(); $table->string('preferred_body_weight_unit', 2)->default('lb');
            $table->string('objective')->default('body_recomposition'); $table->string('experience_level')->default('returning_beginner');
            $table->unsignedTinyInteger('weekly_workout_goal')->default(4); $table->unsignedSmallInteger('target_session_minutes')->default(75);
            $table->unsignedSmallInteger('maximum_session_minutes')->default(90); $table->unsignedSmallInteger('protein_goal_min_grams')->nullable();
            $table->unsignedSmallInteger('protein_goal_max_grams')->nullable(); $table->decimal('sleep_goal_hours', 3, 1)->nullable();
            $table->text('notes')->nullable(); $table->timestamps();
        });

        Schema::create('routines', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('name');
            $table->text('description')->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
            $table->index(['user_id', 'is_active']);
        });
        Schema::create('training_phases', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_id')->nullable()->constrained()->nullOnDelete(); $table->string('name'); $table->text('description')->nullable();
            $table->date('starts_on'); $table->date('ends_on'); $table->string('status')->default('upcoming');
            $table->unsignedSmallInteger('planned_sessions'); $table->unsignedSmallInteger('minimum_target_sessions');
            $table->decimal('target_weight_min_kg', 6, 2)->nullable(); $table->decimal('target_weight_max_kg', 6, 2)->nullable();
            $table->decimal('target_waist_reduction_min_cm', 5, 2)->nullable(); $table->decimal('target_waist_reduction_max_cm', 5, 2)->nullable();
            $table->unsignedSmallInteger('protein_goal_min_grams')->nullable(); $table->unsignedSmallInteger('protein_goal_max_grams')->nullable();
            $table->decimal('sleep_goal_hours', 3, 1)->nullable(); $table->timestamps(); $table->index(['user_id', 'status']);
        });
        Schema::create('body_measurements', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->date('recorded_on');
            $table->decimal('body_weight_kg', 6, 2)->nullable(); $table->decimal('waist_cm', 6, 2)->nullable();
            $table->text('notes')->nullable(); $table->timestamps(); $table->index(['user_id', 'recorded_on']);
        });
        Schema::create('muscle_groups', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->timestamps();
        });
        Schema::create('exercises', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->string('name');
            $table->string('slug'); $table->text('description')->nullable(); $table->text('instructions')->nullable();
            $table->string('equipment')->nullable(); $table->string('image_path')->nullable(); $table->string('metric_type')->default('weight_reps');
            $table->string('weight_mode')->default('machine_stack'); $table->string('default_weight_unit', 2)->nullable();
            $table->decimal('default_increment', 6, 2)->nullable(); $table->boolean('is_active')->default(true); $table->timestamps();
            $table->unique(['user_id', 'slug']); $table->index(['user_id', 'is_active']);
        });
        Schema::create('exercise_muscle_group', function (Blueprint $table) {
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete(); $table->foreignId('muscle_group_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false); $table->primary(['exercise_id', 'muscle_group_id']);
        });
        Schema::create('exercise_alternatives', function (Blueprint $table) {
            $table->id(); $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('alternative_exercise_id')->constrained('exercises')->cascadeOnDelete(); $table->unsignedSmallInteger('position');
            $table->text('notes')->nullable(); $table->timestamps(); $table->unique(['exercise_id', 'alternative_exercise_id']);
        });
        Schema::create('routine_days', function (Blueprint $table) {
            $table->id(); $table->foreignId('routine_id')->constrained()->cascadeOnDelete(); $table->string('name');
            $table->unsignedTinyInteger('weekday'); $table->string('day_type'); $table->unsignedTinyInteger('position');
            $table->unsignedSmallInteger('estimated_minutes')->nullable(); $table->text('notes')->nullable(); $table->timestamps();
            $table->unique(['routine_id', 'weekday']);
        });
        Schema::create('routine_exercises', function (Blueprint $table) {
            $table->id(); $table->foreignId('routine_day_id')->constrained()->cascadeOnDelete(); $table->foreignId('exercise_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('position'); $table->string('priority')->default('essential'); $table->unsignedTinyInteger('target_sets');
            $table->unsignedSmallInteger('minimum_reps')->nullable(); $table->unsignedSmallInteger('maximum_reps')->nullable();
            $table->unsignedSmallInteger('progression_target_reps')->nullable(); $table->unsignedSmallInteger('target_duration_seconds')->nullable();
            $table->decimal('target_weight', 7, 2)->nullable(); $table->string('weight_unit', 2)->nullable();
            $table->unsignedTinyInteger('target_rir_min')->nullable(); $table->unsignedTinyInteger('target_rir_max')->nullable();
            $table->unsignedSmallInteger('rest_seconds')->default(90); $table->decimal('weight_increment', 6, 2)->nullable();
            $table->string('progression_type')->default('double_progression'); $table->string('superset_group')->nullable();
            $table->text('notes')->nullable(); $table->timestamps(); $table->unique(['routine_day_id', 'exercise_id']);
        });
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('routine_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('routine_day_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('training_phase_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); $table->date('scheduled_for')->nullable(); $table->timestamp('started_at'); $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable(); $table->string('status')->default('in_progress'); $table->decimal('sleep_hours', 3, 1)->nullable();
            $table->unsignedTinyInteger('energy_level')->nullable(); $table->unsignedTinyInteger('motivation_level')->nullable(); $table->text('discomfort_notes')->nullable();
            $table->unsignedTinyInteger('session_difficulty')->nullable(); $table->unsignedTinyInteger('session_satisfaction')->nullable();
            $table->boolean('is_atypical')->default(false); $table->string('atypical_reason')->nullable(); $table->text('notes')->nullable();
            $table->decimal('total_volume', 12, 2)->nullable(); $table->unsignedSmallInteger('working_sets_count')->nullable();
            $table->integer('xp_earned')->default(0); $table->integer('power_earned')->default(0); $table->json('routine_snapshot_json')->nullable(); $table->timestamps();
            $table->index(['user_id', 'status']); $table->index(['user_id', 'started_at']);
        });
        Schema::create('workout_exercises', function (Blueprint $table) {
            $table->id(); $table->foreignId('workout_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('planned_exercise_id')->constrained('exercises')->restrictOnDelete(); $table->foreignId('performed_exercise_id')->constrained('exercises')->restrictOnDelete();
            $table->foreignId('routine_exercise_id')->nullable()->constrained()->nullOnDelete(); $table->unsignedSmallInteger('position');
            $table->boolean('was_substituted')->default(false); $table->string('substitution_reason')->nullable();
            $table->json('planned_snapshot_json'); $table->json('previous_performance_json')->nullable(); $table->json('recommendation_snapshot_json')->nullable();
            $table->text('notes')->nullable(); $table->timestamps();
        });
        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id(); $table->foreignId('workout_exercise_id')->constrained()->cascadeOnDelete(); $table->unsignedSmallInteger('set_number');
            $table->string('set_type')->default('working'); $table->decimal('weight', 7, 2)->nullable(); $table->string('weight_unit', 2)->nullable();
            $table->unsignedSmallInteger('repetitions')->nullable(); $table->unsignedSmallInteger('duration_seconds')->nullable(); $table->unsignedTinyInteger('rir')->nullable();
            $table->unsignedTinyInteger('technique_rating')->nullable(); $table->unsignedSmallInteger('rest_seconds_actual')->nullable();
            $table->boolean('completed')->default(true); $table->boolean('is_personal_record')->default(false); $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable(); $table->timestamps(); $table->unique(['workout_exercise_id', 'set_number', 'set_type']);
        });
        Schema::create('progression_recommendations', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('routine_exercise_id')->nullable()->constrained()->nullOnDelete(); $table->foreignId('source_workout_session_id')->nullable()->constrained('workout_sessions')->nullOnDelete();
            $table->string('recommendation_type'); $table->decimal('current_weight', 7, 2)->nullable(); $table->decimal('suggested_weight', 7, 2)->nullable();
            $table->string('weight_unit', 2)->nullable(); $table->unsignedSmallInteger('suggested_total_repetitions')->nullable(); $table->text('reason');
            $table->string('confidence'); $table->string('status')->default('pending'); $table->json('metadata_json')->nullable(); $table->timestamps();
            $table->timestamp('accepted_at')->nullable(); $table->index(['user_id', 'status']);
        });
        Schema::create('personal_records', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workout_set_id')->nullable()->constrained()->nullOnDelete(); $table->string('record_type'); $table->decimal('value', 12, 2);
            $table->string('weight_unit', 2)->nullable(); $table->timestamp('achieved_at'); $table->timestamps(); $table->unique(['user_id', 'exercise_id', 'record_type']);
        });
        Schema::create('avatar_phases', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->unsignedTinyInteger('position')->unique();
            $table->text('description'); $table->unsignedInteger('minimum_xp'); $table->unsignedSmallInteger('minimum_completed_workouts');
            $table->unsignedSmallInteger('minimum_perfect_weeks')->default(0); $table->boolean('requires_completed_phase')->default(false);
            $table->json('visual_config_json'); $table->timestamps();
        });
        Schema::create('game_profiles', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete(); $table->unsignedInteger('total_xp')->default(0);
            $table->unsignedSmallInteger('level')->default(1); $table->unsignedTinyInteger('energy')->default(100); $table->unsignedSmallInteger('combat_power')->default(0);
            $table->unsignedSmallInteger('current_weekly_streak')->default(0); $table->unsignedSmallInteger('best_weekly_streak')->default(0);
            $table->unsignedSmallInteger('perfect_weeks')->default(0); $table->foreignId('maximum_avatar_phase_id')->nullable()->constrained('avatar_phases')->nullOnDelete();
            $table->foreignId('active_avatar_phase_id')->nullable()->constrained('avatar_phases')->nullOnDelete(); $table->timestamp('last_activity_at')->nullable(); $table->timestamps();
        });
        Schema::create('achievements', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->string('slug')->unique(); $table->text('description'); $table->string('icon');
            $table->unsignedSmallInteger('xp_reward'); $table->string('criteria_type'); $table->unsignedInteger('criteria_value')->nullable();
            $table->json('unlock_config_json')->nullable(); $table->timestamps();
        });
        Schema::create('xp_events', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('workout_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('achievement_id')->nullable()->constrained()->nullOnDelete(); $table->string('event_type'); $table->string('event_key')->unique();
            $table->integer('amount'); $table->string('description'); $table->timestamps(); $table->index(['user_id', 'created_at']);
        });
        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id(); $table->foreignId('user_id')->constrained()->cascadeOnDelete(); $table->foreignId('achievement_id')->constrained()->cascadeOnDelete();
            $table->timestamp('unlocked_at'); $table->timestamps(); $table->unique(['user_id', 'achievement_id']);
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['user_achievements','xp_events','achievements','game_profiles','avatar_phases','personal_records','progression_recommendations','workout_sets','workout_exercises','workout_sessions','routine_exercises','routine_days','exercise_alternatives','exercise_muscle_group','exercises','muscle_groups','body_measurements','training_phases','routines','user_profiles'] as $table) Schema::dropIfExists($table);
        Schema::enableForeignKeyConstraints();
    }
};
