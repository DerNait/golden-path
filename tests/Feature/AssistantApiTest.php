<?php

namespace Tests\Feature;

use App\Models\ProgressionRecommendation;
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
    }

    public function test_recommendations_can_be_filtered_by_status(): void
    {
        $token = $this->token(['training:read']);
        $base = ['user_id' => $this->user->id, 'exercise_id' => 1, 'recommendation_type' => 'maintain',
            'confidence' => 'low', 'weight_unit' => 'lb'];
        ProgressionRecommendation::create($base + ['reason' => 'pendiente', 'status' => 'pending']);
        ProgressionRecommendation::create($base + ['reason' => 'aceptada', 'status' => 'accepted']);
        ProgressionRecommendation::create($base + ['reason' => 'ignorada', 'status' => 'ignored']);

        // Pending by default.
        $this->bearer($token)->getJson('/api/v1/assistant/recommendations')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 'pending');

        $this->bearer($token)->getJson('/api/v1/assistant/recommendations?status=accepted')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reason', 'aceptada');

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

    public function test_write_creates_pending_draft_supersedes_engine_and_applies_on_accept(): void
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
                'exercise_id' => $exerciseId, 'recommendation_type' => 'increase_weight', 'confidence' => 'high',
                'reason' => 'Cerraste el rango con RIR 2.', 'suggested_weight' => 75,
                'data_period' => 'ultimas 4 semanas', 'provider' => 'anthropic', 'model' => 'claude-opus-4-8',
            ]],
        ])->assertCreated();

        $draftId = $response->json('created.0.id');
        $draft = ProgressionRecommendation::findOrFail($draftId);

        $this->assertSame('superseded', $prior->fresh()->status);
        $this->assertSame('pending', $draft->status);
        $this->assertSame('assistant', $draft->metadata_json['source']);
        $this->assertSame($routineExercise->id, $draft->routine_exercise_id);
        $this->assertDatabaseHas('assistant_activity_logs', ['user_id' => $this->user->id, 'ability' => 'recommendations:write']);

        // Accept through the existing session flow -> routine target updated.
        $this->actingAs($this->user)->postJson("/api/progression/recommendations/{$draftId}/accept")->assertOk();
        $this->assertEqualsWithDelta(75.0, (float) $routineExercise->fresh()->target_weight, 0.001);
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
