<?php

namespace Tests\Feature;

use App\Models\ExerciseTarget;
use App\Models\ProgressionRecommendation;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::where('email', 'owner@example.com')->firstOrFail();
    }

    private function token(array $abilities): string
    {
        return $this->user->createToken('test', $abilities)->plainTextToken;
    }

    private function bearer(string $token): self
    {
        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_missing_token_is_unauthorized(): void
    {
        $this->getJson('/api/v1/assistant/today')->assertUnauthorized();
    }

    public function test_read_endpoints_work_with_training_read_and_are_scoped(): void
    {
        $token = $this->token(['training:read']);

        $this->bearer($token)->getJson('/api/v1/assistant/today')
            ->assertOk()->assertJsonStructure(['date', 'is_training_day', 'exercises']);
        $this->bearer($token)->getJson('/api/v1/assistant/workouts/recent?limit=5')
            ->assertOk()->assertJsonStructure(['data']);
        $this->bearer($token)->getJson('/api/v1/assistant/training-context')
            ->assertOk()->assertJsonPath('profile.age', 22);
        $this->bearer($token)->getJson('/api/v1/assistant/body-stats')
            ->assertOk()->assertJsonStructure(['latest', 'trend']);
        $this->bearer($token)->getJson('/api/v1/assistant/exercises/1/history')
            ->assertOk()->assertJsonStructure(['exercise', 'calibration', 'series', 'records']);
        $this->bearer($token)->getJson('/api/v1/assistant/recommendations')
            ->assertOk()->assertJsonStructure(['data']);
        $this->bearer($token)->getJson('/api/v1/assistant/routine')
            ->assertOk()->assertJsonStructure(['routine', 'days' => [['name', 'exercises']], 'exercise_targets', 'multi_set_exercises']);
    }

    public function test_recommendations_can_be_filtered_by_status(): void
    {
        $token = $this->token(['training:read']);
        $base = ['user_id' => $this->user->id, 'exercise_id' => 1, 'recommendation_type' => 'maintain',
            'confidence' => 'low', 'weight_unit' => 'lb'];
        ProgressionRecommendation::create($base + ['reason' => 'pendiente', 'status' => 'pending']);
        ProgressionRecommendation::create($base + ['reason' => 'aceptada', 'status' => 'accepted']);
        ProgressionRecommendation::create($base + ['reason' => 'ignorada', 'status' => 'ignored']);

        // Applied (accepted) by default: published recommendations take effect at once.
        $this->bearer($token)->getJson('/api/v1/assistant/recommendations')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'accepted');

        $this->bearer($token)->getJson('/api/v1/assistant/recommendations?status=pending')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reason', 'pendiente');

        $this->bearer($token)->getJson('/api/v1/assistant/recommendations?status=pending,accepted')
            ->assertOk()->assertJsonCount(2, 'data');

        $this->bearer($token)->getJson('/api/v1/assistant/recommendations?status=all')
            ->assertOk()->assertJsonCount(3, 'data');

        $this->bearer($token)->getJson('/api/v1/assistant/recommendations?status=bogus')
            ->assertStatus(422);

        // Clients that forget the JSON header must still get the error, not a redirect.
        $this->bearer($token)->get('/api/v1/assistant/recommendations?status=bogus')
            ->assertStatus(422);
    }

    public function test_training_read_token_cannot_write_drafts(): void
    {
        $token = $this->token(['training:read']);

        $this->bearer($token)->postJson('/api/v1/assistant/recommendation-drafts', [
            'drafts' => [[
                'exercise_id' => 1, 'recommendation_type' => 'maintain', 'confidence' => 'low',
                'reason' => 'x', 'provider' => 'anthropic', 'model' => 'claude-opus-4-8',
            ]],
        ])->assertForbidden();
    }

    public function test_published_recommendation_applies_immediately_and_supersedes_pending(): void
    {
        $routineExercise = RoutineExercise::whereHas('routineDay.routine', fn ($q) => $q->where('user_id', $this->user->id)->where('is_active', true))
            ->whereHas('exercise', fn ($q) => $q->where('metric_type', 'weight_reps'))
            ->orderBy('routine_day_id')->orderBy('position')->firstOrFail();
        $exerciseId = $routineExercise->exercise_id;

        $prior = ProgressionRecommendation::create([
            'user_id' => $this->user->id, 'exercise_id' => $exerciseId, 'routine_exercise_id' => $routineExercise->id,
            'recommendation_type' => 'maintain', 'reason' => 'engine prior', 'confidence' => 'low', 'status' => 'pending',
        ]);

        $token = $this->token(['recommendations:write']);
        $response = $this->bearer($token)->postJson('/api/v1/assistant/recommendation-drafts', [
            'drafts' => [[
                'exercise_id' => $exerciseId, 'target_sets' => $routineExercise->target_sets,
                'recommendation_type' => 'increase_weight', 'confidence' => 'high',
                'reason' => 'Cerraste el rango con RIR 2.', 'suggested_weight' => 75,
                'data_period' => 'ultimas 4 semanas', 'provider' => 'anthropic', 'model' => 'claude-opus-4-8',
            ]],
        ])->assertCreated()
            ->assertJsonPath('created.0.status', 'accepted')
            ->assertJsonPath('created.0.applied', true)
            ->assertJsonPath('created.0.target_sets', $routineExercise->target_sets);

        $recommendation = ProgressionRecommendation::findOrFail($response->json('created.0.id'));

        $this->assertSame('superseded', $prior->fresh()->status);
        $this->assertSame('accepted', $recommendation->status);
        $this->assertNotNull($recommendation->accepted_at);
        $this->assertSame('assistant', $recommendation->metadata_json['source']);
        $this->assertSame($routineExercise->id, $recommendation->routine_exercise_id);
        $this->assertDatabaseHas('assistant_activity_logs', ['user_id' => $this->user->id, 'ability' => 'recommendations:write']);

        // No acceptance step: the routine target already carries the new load.
        $this->assertEqualsWithDelta(75.0, (float) $routineExercise->fresh()->target_weight, 0.001);
        $this->assertDatabaseHas('exercise_targets', [
            'user_id' => $this->user->id, 'exercise_id' => $exerciseId, 'target_sets' => $routineExercise->target_sets,
        ]);
    }

    /** Sentadilla bulgara at 2 sets on Lower B (seeded) plus 3 sets on Lower A. */
    private function bulgarianAtTwoSetCounts(): array
    {
        $lowerB = RoutineExercise::whereHas('exercise', fn ($q) => $q->where('name', 'Sentadilla bulgara'))->firstOrFail();
        $lowerA = RoutineDay::where('routine_id', $lowerB->routineDay->routine_id)->where('name', 'Lower A')->firstOrFail();
        $threeSets = $lowerA->exercises()->create([
            'exercise_id' => $lowerB->exercise_id, 'position' => 99, 'priority' => 'essential',
            'target_sets' => 3, 'minimum_reps' => 6, 'maximum_reps' => 10, 'progression_target_reps' => 10,
            'target_weight' => 15, 'weight_unit' => 'lb', 'weight_increment' => 5, 'rest_seconds' => 150,
        ]);

        return [$threeSets, $lowerB];
    }

    public function test_target_sets_is_required_and_must_be_a_count_the_routine_uses(): void
    {
        [$threeSets] = $this->bulgarianAtTwoSetCounts();
        $token = $this->token(['recommendations:write']);
        $draft = ['exercise_id' => $threeSets->exercise_id, 'recommendation_type' => 'increase_repetitions', 'confidence' => 'medium',
            'reason' => 'x', 'suggested_total_repetitions' => 20, 'provider' => 'openai', 'model' => 'gpt-5'];

        $this->bearer($token)->postJson('/api/v1/assistant/recommendation-drafts', ['drafts' => [$draft]])
            ->assertStatus(422)->assertJsonValidationErrors('drafts.0.target_sets');

        // Planned at 2 and 3 sets only: a 4-set goal would never be read.
        $this->bearer($token)->postJson('/api/v1/assistant/recommendation-drafts', ['drafts' => [$draft + ['target_sets' => 4]]])
            ->assertStatus(422)->assertJsonValidationErrors('drafts.0.target_sets');
    }

    public function test_alternative_uses_the_set_count_of_the_slot_it_replaces(): void
    {
        $slot = RoutineExercise::whereHas('routineDay.routine', fn ($q) => $q->where('user_id', $this->user->id)->where('is_active', true))
            ->whereHas('exercise.alternativeExercises')->orderBy('routine_day_id')->orderBy('position')->firstOrFail();
        $alternative = $slot->exercise->alternativeExercises()->firstOrFail();

        $this->bearer($this->token(['recommendations:write']))->postJson('/api/v1/assistant/recommendation-drafts', ['drafts' => [[
            'exercise_id' => $alternative->id, 'target_sets' => $slot->target_sets, 'recommendation_type' => 'increase_weight',
            'confidence' => 'medium', 'reason' => 'Alternativa lista para subir.', 'suggested_weight' => 40,
            'provider' => 'openai', 'model' => 'gpt-5',
        ]]])->assertCreated();

        $this->assertDatabaseHas('exercise_targets', [
            'user_id' => $this->user->id, 'exercise_id' => $alternative->id, 'target_sets' => $slot->target_sets,
        ]);
        // The planned exercise keeps its own slot target.
        $this->assertNotEquals(40.0, (float) $slot->fresh()->target_weight);
    }

    public function test_one_recommendation_per_set_count_updates_each_slot_and_target(): void
    {
        [$threeSets, $twoSets] = $this->bulgarianAtTwoSetCounts();
        $base = ['exercise_id' => $threeSets->exercise_id, 'recommendation_type' => 'increase_repetitions',
            'confidence' => 'medium', 'provider' => 'openai', 'model' => 'gpt-5'];

        $token = $this->token(['training:read', 'recommendations:write']);
        $this->bearer($token)->getJson('/api/v1/assistant/routine')
            ->assertOk()->assertJsonFragment(['set_counts' => [2, 3]]);

        $this->bearer($token)->postJson('/api/v1/assistant/recommendation-drafts', ['drafts' => [
            $base + ['target_sets' => 3, 'reason' => 'Lower A', 'suggested_total_repetitions' => 23, 'suggested_rep_distribution' => [8, 8, 7]],
            $base + ['target_sets' => 2, 'reason' => 'Lower B', 'suggested_weight' => 20, 'suggested_total_repetitions' => 16, 'suggested_rep_distribution' => [8, 8]],
        ]])->assertCreated()->assertJsonCount(2, 'created');

        $this->assertSame(23, $threeSets->fresh()->progression_target_total_reps);
        $this->assertEqualsWithDelta(15.0, (float) $threeSets->fresh()->target_weight, 0.001);
        $this->assertEqualsWithDelta(20.0, (float) $twoSets->fresh()->target_weight, 0.001);
        $this->assertSame(16, $twoSets->fresh()->progression_target_total_reps);

        $targets = ExerciseTarget::where('user_id', $this->user->id)->where('exercise_id', $threeSets->exercise_id)->get()->keyBy('target_sets');
        $this->assertSame([8, 8, 7], $targets[3]->rep_distribution);
        $this->assertSame([8, 8], $targets[2]->rep_distribution);
        $this->assertEqualsWithDelta(20.0, (float) $targets[2]->target_weight, 0.001);
        // The 2-set change must not leak into the 3-set goal.
        $this->assertNull($targets[3]->target_weight);
    }

    public function test_distribution_must_match_target_sets(): void
    {
        [$threeSets] = $this->bulgarianAtTwoSetCounts();

        $this->bearer($this->token(['recommendations:write']))->postJson('/api/v1/assistant/recommendation-drafts', [
            'drafts' => [[
                'exercise_id' => $threeSets->exercise_id, 'target_sets' => 3, 'recommendation_type' => 'increase_repetitions',
                'confidence' => 'medium', 'reason' => 'x', 'suggested_total_repetitions' => 16,
                'suggested_rep_distribution' => [8, 8], 'provider' => 'openai', 'model' => 'gpt-5',
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('drafts.0.suggested_rep_distribution');
    }

    public function test_write_rejects_invalid_enum(): void
    {
        $token = $this->token(['recommendations:write']);

        $this->bearer($token)->postJson('/api/v1/assistant/recommendation-drafts', [
            'drafts' => [[
                'exercise_id' => 1, 'recommendation_type' => 'bogus', 'confidence' => 'high',
                'reason' => 'x', 'provider' => 'anthropic', 'model' => 'claude-opus-4-8',
            ]],
        ])->assertStatus(422);
    }
}
