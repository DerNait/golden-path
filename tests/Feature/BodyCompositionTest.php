<?php

namespace Tests\Feature;

use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BodyCompositionTest extends TestCase
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

    public function test_volume_uses_snapshotted_body_weight_and_leaves_loaded_lifts_unchanged(): void
    {
        $bodyweight = new WorkoutSet(['completed' => true, 'set_type' => 'working', 'weight' => null, 'body_weight_kg' => 60, 'repetitions' => 10]);
        $this->assertSame(600.0, $bodyweight->volume);
        $this->assertNull($bodyweight->estimated_one_rep_max);

        $loaded = new WorkoutSet(['completed' => true, 'set_type' => 'working', 'weight' => 20, 'body_weight_kg' => null, 'repetitions' => 10]);
        $this->assertSame(200.0, $loaded->volume);

        $warmup = new WorkoutSet(['completed' => true, 'set_type' => 'warmup', 'weight' => null, 'body_weight_kg' => 60, 'repetitions' => 10]);
        $this->assertSame(0.0, $warmup->volume);
    }

    public function test_bodyweight_set_snapshots_current_body_weight_into_volume(): void
    {
        $this->user->profile->update(['current_body_weight_kg' => 60]);

        $routineExercise = RoutineExercise::whereHas('exercise', fn ($q) => $q->where('metric_type', 'bodyweight_reps'))->firstOrFail();
        $exercise = $routineExercise->exercise;
        $day = RoutineDay::findOrFail($routineExercise->routine_day_id);

        $started = $this->postJson('/api/workouts/start', ['routine_day_id' => $day->id])->assertCreated()->json('data');
        $target = collect($started['exercises'])->first(fn ($item) => $item['performed_exercise']['id'] === $exercise->id);
        $this->assertNotNull($target, 'Bodyweight exercise not present in started session.');

        $set = $this->postJson("/api/workout-exercises/{$target['id']}/sets", [
            'set_number' => 1, 'set_type' => 'working', 'repetitions' => 10, 'rir' => 2, 'completed' => true,
        ])->assertCreated()->json('set');

        $this->assertSame(600.0, (float) $set['volume']);
        $this->assertSame('60.00', $set['body_weight_kg']);
    }

    public function test_body_measurement_stores_composition_and_is_scoped(): void
    {
        $payload = [
            'recorded_on' => now()->subDay()->toDateString(),
            'body_weight_kg' => 70.0, 'waist_cm' => 80.0, 'body_fat_percentage' => 20.0,
            'fat_mass_kg' => 14.0, 'lean_mass_kg' => 56.0, 'skeletal_muscle_mass_kg' => 30.0,
            'total_body_water_kg' => 40.0, 'visceral_fat_level' => 7, 'basal_metabolic_rate_kcal' => 1600,
        ];

        $this->postJson('/api/body-measurements', $payload)->assertCreated();

        $this->assertDatabaseHas('body_measurements', [
            'user_id' => $this->user->id, 'body_fat_percentage' => 20.0,
            'skeletal_muscle_mass_kg' => 30.0, 'visceral_fat_level' => 7, 'basal_metabolic_rate_kcal' => 1600,
        ]);

        $summary = $this->getJson('/api/progress/body')->assertOk()->json('summary');
        $this->assertSame('20.00', (string) $summary['current_body_fat']);
        $this->assertSame('30.00', (string) $summary['current_muscle']);
    }
}
