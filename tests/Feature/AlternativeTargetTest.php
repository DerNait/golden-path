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

    public function test_target_falls_back_to_the_last_load_when_the_slot_has_none(): void
    {
        $slot = $this->slotFor('Lower A', 2); // planned exercise, no target weight yet
        $slot->update(['target_weight' => null, 'progression_target_total_reps' => 30, 'target_sets' => 3]);

        $day = RoutineDay::where('name', 'Lower A')->firstOrFail();
        $session = $this->postJson('/api/workouts/start', ['routine_day_id' => $day->id])->assertCreated()->json('data');
        $workoutExerciseId = collect($session['exercises'])->firstWhere('planned_exercise.id', $slot->exercise_id)['id'];
        $this->postJson("/api/workout-exercises/{$workoutExerciseId}/sets", [
            'set_number' => 1, 'set_type' => 'working', 'weight' => 80, 'weight_unit' => 'lb',
            'repetitions' => 10, 'rir' => 1, 'completed' => true,
        ])->assertCreated();
        $this->postJson("/api/workouts/{$session['id']}/finish", [])->assertOk();

        $resolved = app(ExerciseTargetService::class)->resolve($this->user, $slot->exercise, $slot->fresh());

        $this->assertSame(80.0, $resolved['weight']);          // last load actually used
        $this->assertSame(30, $resolved['total_reps']);        // goal still comes from the slot
        $this->assertSame('last_performance', $resolved['source']);
    }

    public function test_accepted_assistant_recommendation_still_shows_during_training(): void
    {
        $slot = $this->slotFor('Upper A', 1);
        $recommendation = ProgressionRecommendation::create([
            'user_id' => $this->user->id, 'exercise_id' => $slot->exercise_id, 'routine_exercise_id' => $slot->id,
            'recommendation_type' => 'increase_repetitions', 'suggested_total_repetitions' => 29, 'weight_unit' => 'lb',
            'reason' => 'Manten 25 lb y busca 29 totales.', 'confidence' => 'high', 'status' => 'pending',
            'metadata_json' => ['source' => 'assistant', 'provider' => 'anthropic', 'model' => 'test'],
        ]);
        $this->postJson("/api/progression/recommendations/{$recommendation->id}/accept")->assertOk();

        $day = RoutineDay::where('name', 'Upper A')->firstOrFail();
        $session = $this->postJson('/api/workouts/start', ['routine_day_id' => $day->id])->assertCreated()->json('data');
        $first = collect($session['exercises'])->firstWhere('planned_exercise.id', $slot->exercise_id);

        $this->assertSame('Manten 25 lb y busca 29 totales.', $first['assistant_recommendation']['reason']);
    }

    public function test_rep_distribution_is_derived_and_front_loads_the_remainder(): void
    {
        $this->assertSame([10, 10, 10], ExerciseTargetService::distribute(30, 3));
        $this->assertSame([10, 10, 9], ExerciseTargetService::distribute(29, 3));
        $this->assertSame([11, 10], ExerciseTargetService::distribute(21, 2));
        $this->assertSame([], ExerciseTargetService::distribute(0, 3));

        $slot = $this->slotFor('Upper A', 1);
        $slot->update(['target_sets' => 3, 'progression_target_total_reps' => 29]);

        $resolved = app(ExerciseTargetService::class)->resolve($this->user, $slot->exercise, $slot->fresh());
        $this->assertSame([10, 10, 9], $resolved['rep_distribution']);
    }

    public function test_accepted_distribution_is_kept_for_that_set_count(): void
    {
        $slot = $this->slotFor('Upper A', 1);
        $slot->update(['target_sets' => 3, 'target_weight' => 25]);

        $recommendation = ProgressionRecommendation::create([
            'user_id' => $this->user->id, 'exercise_id' => $slot->exercise_id, 'routine_exercise_id' => $slot->id,
            'recommendation_type' => 'increase_repetitions', 'suggested_total_repetitions' => 29,
            'suggested_rep_distribution' => [12, 9, 8], 'weight_unit' => 'lb',
            'reason' => 'Carga la primera serie.', 'confidence' => 'high', 'status' => 'pending',
        ]);

        $this->postJson("/api/progression/recommendations/{$recommendation->id}/accept")->assertOk();

        // The proposed split wins over the even one for those sets.
        $resolved = app(ExerciseTargetService::class)->resolve($this->user, $slot->exercise, $slot->fresh());
        $this->assertSame([12, 9, 8], $resolved['rep_distribution']);
    }

    public function test_assistant_can_send_a_rep_distribution_and_totals_must_match(): void
    {
        $slot = $this->slotFor('Upper A', 1);
        $token = $this->user->createToken('test', ['recommendations:write'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/assistant/recommendation-drafts', ['drafts' => [[
                'exercise_id' => $slot->exercise_id, 'target_sets' => 3, 'recommendation_type' => 'increase_repetitions',
                'confidence' => 'high', 'reason' => 'Reparte asi.', 'suggested_rep_distribution' => [10, 10, 9],
                'provider' => 'anthropic', 'model' => 'test',
            ]]])->assertCreated()->assertJsonPath('created.0.suggested_total_repetitions', 29);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/assistant/recommendation-drafts', ['drafts' => [[
                'exercise_id' => $slot->exercise_id, 'recommendation_type' => 'increase_repetitions',
                'confidence' => 'high', 'reason' => 'No cuadra.', 'suggested_total_repetitions' => 30,
                'suggested_rep_distribution' => [10, 10, 9], 'provider' => 'anthropic', 'model' => 'test',
            ]]])->assertStatus(422);
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

    public function test_training_shows_the_recommendation_for_that_days_set_count(): void
    {
        $planned = $this->slotFor('Upper A', 1);
        $planned->update(['target_sets' => 3]);
        $base = ['user_id' => $this->user->id, 'exercise_id' => $planned->exercise_id, 'recommendation_type' => 'increase_repetitions',
            'weight_unit' => 'lb', 'confidence' => 'high', 'status' => 'accepted', 'metadata_json' => ['source' => 'assistant']];
        ProgressionRecommendation::create($base + ['target_sets' => 3, 'reason' => 'Para tres series.']);
        // Newer, but written for the day that plans two sets.
        ProgressionRecommendation::create($base + ['target_sets' => 2, 'reason' => 'Para dos series.']);

        $day = RoutineDay::where('name', 'Upper A')->firstOrFail();
        $session = $this->postJson('/api/workouts/start', ['routine_day_id' => $day->id])->assertCreated()->json('data');
        $first = collect($session['exercises'])->firstWhere('planned_exercise.id', $planned->exercise_id);

        $this->assertSame('Para tres series.', $first['assistant_recommendation']['reason']);
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
