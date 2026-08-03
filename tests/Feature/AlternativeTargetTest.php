<?php

namespace Tests\Feature;

use App\Models\ExerciseTarget;
use App\Models\ProgressionRecommendation;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Services\Progression\ExerciseTargetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlternativeTargetTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::where('email', 'owner@example.com')->firstOrFail();
        $this->actingAs($this->user);
    }

    private function slotFor(string $day, int $position): RoutineExercise
    {
        return RoutineExercise::where('routine_day_id', RoutineDay::where('name', $day)->value('id'))
            ->where('position', $position)->firstOrFail();
    }

    public function test_alternative_borrows_its_own_target_scaled_to_todays_sets(): void
    {
        $planned = $this->slotFor('Upper A', 1);      // 3 sets
        $home = $this->slotFor('Upper B', 4);          // the alternative's own slot, 2 sets
        $home->update(['target_weight' => 45, 'progression_target_total_reps' => 20, 'target_sets' => 2]);
        $planned->update(['target_weight' => 25, 'progression_target_total_reps' => 29, 'target_sets' => 3]);

        $resolved = app(ExerciseTargetService::class)->resolve($this->user, $home->exercise, $planned);

        // Takes the alternative's own load, never the planned exercise's 25 lb.
        $this->assertSame(45.0, $resolved['weight']);
        $this->assertSame('home_slot', $resolved['source']);
        $this->assertSame(3, $resolved['sets']);
        $this->assertSame(30, $resolved['total_reps']); // 20 reps / 2 sets * 3 sets
    }

    public function test_target_is_stored_per_set_count(): void
    {
        $exercise = $this->slotFor('Upper B', 4)->exercise;
        $targets = app(ExerciseTargetService::class);

        $targets->remember($this->user, $exercise, 3, 40.0, 'lb', 30);
        $targets->remember($this->user, $exercise, 2, 45.0, 'lb', 20);

        $threeSets = $this->slotFor('Upper A', 1);
        $threeSets->update(['target_sets' => 3]);
        $twoSets = $this->slotFor('Upper B', 4);
        $twoSets->update(['target_sets' => 2]);

        $this->assertSame(40.0, $targets->resolve($this->user, $exercise, $threeSets)['weight']);
        $this->assertSame(45.0, $targets->resolve($this->user, $exercise, $twoSets)['weight']);
    }

    public function test_accepting_a_substituted_recommendation_does_not_overwrite_the_planned_slot(): void
    {
        $planned = $this->slotFor('Upper A', 1);
        $planned->update(['target_weight' => 25, 'target_sets' => 3]);
        $alternative = $this->slotFor('Upper B', 4)->exercise;

        // Recommendation about the alternative, recorded against the planned slot.
        $recommendation = ProgressionRecommendation::create([
            'user_id' => $this->user->id, 'exercise_id' => $alternative->id, 'routine_exercise_id' => $planned->id,
            'recommendation_type' => 'increase_weight', 'suggested_weight' => 50, 'weight_unit' => 'lb',
            'reason' => 'Alternativa dominada.', 'confidence' => 'high', 'status' => 'pending',
        ]);

        $this->postJson("/api/progression/recommendations/{$recommendation->id}/accept")->assertOk();

        $this->assertEqualsWithDelta(25.0, (float) $planned->fresh()->target_weight, 0.001);
        $this->assertDatabaseHas('exercise_targets', [
            'user_id' => $this->user->id, 'exercise_id' => $alternative->id, 'target_sets' => 3, 'target_weight' => 50,
        ]);
    }

    public function test_accepting_a_planned_recommendation_still_updates_the_slot(): void
    {
        $planned = $this->slotFor('Upper A', 1);
        $planned->update(['target_weight' => 25, 'target_sets' => 3]);

        $recommendation = ProgressionRecommendation::create([
            'user_id' => $this->user->id, 'exercise_id' => $planned->exercise_id, 'routine_exercise_id' => $planned->id,
            'recommendation_type' => 'increase_weight', 'suggested_weight' => 30, 'weight_unit' => 'lb',
            'reason' => 'Objetivo cumplido.', 'confidence' => 'high', 'status' => 'pending',
        ]);

        $this->postJson("/api/progression/recommendations/{$recommendation->id}/accept")->assertOk();

        $this->assertEqualsWithDelta(30.0, (float) $planned->fresh()->target_weight, 0.001);
        $this->assertDatabaseHas('exercise_targets', [
            'user_id' => $this->user->id, 'exercise_id' => $planned->exercise_id, 'target_sets' => 3,
        ]);
    }

    public function test_alternative_without_its_own_slot_uses_the_sets_the_routine_asks_for(): void
    {
        $planned = $this->slotFor('Upper A', 1);
        $planned->update(['target_sets' => 3]);
        $alternative = $planned->exercise->alternativeExercises()->firstOrFail();
        RoutineExercise::where('exercise_id', $alternative->id)->delete(); // trained only as an alternative

        // Perform it as an alternative, logging fewer sets than planned.
        $day = RoutineDay::where('name', 'Upper A')->firstOrFail();
        $session = $this->postJson('/api/workouts/start', ['routine_day_id' => $day->id])->assertCreated()->json('data');
        $workoutExerciseId = collect($session['exercises'])->firstWhere('planned_exercise.id', $planned->exercise_id)['id'];
        $this->postJson("/api/workout-exercises/{$workoutExerciseId}/substitute", [
            'alternative_exercise_id' => $alternative->id, 'reason' => 'equipment_busy',
        ])->assertOk();

        // A draft with no slot of its own must still land on the routine's sets.
        $recommendation = ProgressionRecommendation::create([
            'user_id' => $this->user->id, 'exercise_id' => $alternative->id, 'routine_exercise_id' => null,
            'recommendation_type' => 'increase_weight', 'suggested_weight' => 70, 'weight_unit' => 'lb',
            'reason' => 'Alternativa lista para subir.', 'confidence' => 'medium', 'status' => 'pending',
        ]);

        $this->postJson("/api/progression/recommendations/{$recommendation->id}/accept")->assertOk();

        $this->assertDatabaseHas('exercise_targets', [
            'user_id' => $this->user->id, 'exercise_id' => $alternative->id,
            'target_sets' => 3, 'target_weight' => 70,
        ]);
    }

    public function test_workout_exposes_resolved_target_and_assistant_recommendation(): void
    {
        $planned = $this->slotFor('Upper A', 1);
        $planned->update(['target_weight' => 25, 'target_sets' => 3]);

        ProgressionRecommendation::create([
            'user_id' => $this->user->id, 'exercise_id' => $planned->exercise_id, 'routine_exercise_id' => $planned->id,
            'recommendation_type' => 'increase_repetitions', 'suggested_total_repetitions' => 29, 'weight_unit' => 'lb',
            'reason' => 'Sube a 29 reps totales.', 'confidence' => 'high', 'status' => 'pending',
            'metadata_json' => ['source' => 'assistant', 'provider' => 'anthropic', 'model' => 'test'],
        ]);

        $day = RoutineDay::where('name', 'Upper A')->firstOrFail();
        $session = $this->postJson('/api/workouts/start', ['routine_day_id' => $day->id])->assertCreated()->json('data');
        $first = collect($session['exercises'])->firstWhere('planned_exercise.id', $planned->exercise_id);

        $this->assertSame(25.0, (float) $first['target']['weight']);
        $this->assertSame(3, $first['target']['sets']);
        $this->assertSame('Sube a 29 reps totales.', $first['assistant_recommendation']['reason']);
    }

    public function test_substituting_swaps_the_target_to_the_alternative(): void
    {
        $planned = $this->slotFor('Upper A', 1);
        $planned->update(['target_weight' => 25, 'target_sets' => 3]);
        $alternative = $planned->exercise->alternativeExercises()->firstOrFail();
        ExerciseTarget::create([
            'user_id' => $this->user->id, 'exercise_id' => $alternative->id, 'target_sets' => 3,
            'target_weight' => 60, 'weight_unit' => 'lb',
        ]);

        $day = RoutineDay::where('name', 'Upper A')->firstOrFail();
        $session = $this->postJson('/api/workouts/start', ['routine_day_id' => $day->id])->assertCreated()->json('data');
        $workoutExerciseId = collect($session['exercises'])->firstWhere('planned_exercise.id', $planned->exercise_id)['id'];

        $updated = $this->postJson("/api/workout-exercises/{$workoutExerciseId}/substitute", [
            'alternative_exercise_id' => $alternative->id, 'reason' => 'equipment_busy',
        ])->assertOk()->json('data');

        $this->assertSame(60.0, (float) $updated['target']['weight']);
        $this->assertNotSame(25.0, (float) $updated['target']['weight']);
    }
}
