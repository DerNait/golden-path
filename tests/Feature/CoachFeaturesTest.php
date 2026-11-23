<?php

namespace Tests\Feature;

use App\Models\AssistantNote;
use App\Models\Exercise;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Rotation, honest durations, volume per muscle and the assistant's write API. */
class CoachFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user = User::where('email', 'owner@example.com')->firstOrFail();
    }

    private function trainingDays()
    {
        return RoutineDay::whereHas('routine', fn ($q) => $q->where('user_id', $this->user->id)->where('is_active', true))
            ->where('day_type', 'training')->orderBy('position')->get();
    }

    private function makeSession(RoutineDay $day, string $startedAt, string $status = 'completed'): WorkoutSession
    {
        return $this->user->workouts()->create([
            'routine_id' => $day->routine_id, 'routine_day_id' => $day->id, 'name' => $day->name,
            'scheduled_for' => substr($startedAt, 0, 10), 'started_at' => $startedAt, 'status' => $status,
        ]);
    }

    private function bearer(array $abilities): self
    {
        // Each call may use a different token; Laravel would otherwise keep the first user/token it resolved.
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->user->createToken('claude', $abilities)->plainTextToken);
    }

    // ---- rotation ---------------------------------------------------------

    public function test_next_session_follows_the_rotation_not_the_weekday(): void
    {
        $days = $this->trainingDays();
        $this->actingAs($this->user);

        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('today.id', $days[0]->id);

        $this->makeSession($days[0], now()->subDays(3)->toDateTimeString());
        $this->getJson('/api/dashboard')->assertJsonPath('today.id', $days[1]->id);

        $this->makeSession($days[1], now()->subDays(1)->toDateTimeString());
        $this->getJson('/api/dashboard')->assertJsonPath('today.id', $days[2]->id);

        $this->makeSession($days[$days->count() - 1], now()->toDateTimeString());
        $this->getJson('/api/dashboard')->assertJsonPath('today.id', $days[0]->id)->assertJsonPath('trained_today', true);
    }

    public function test_rotation_restarts_after_a_long_break(): void
    {
        $days = $this->trainingDays();
        $this->makeSession($days[1], now()->subDays(60)->toDateTimeString());

        $this->actingAs($this->user)->getJson('/api/dashboard')->assertJsonPath('today.id', $days[0]->id);
        $this->actingAs($this->user)->getJson('/api/routine')->assertJsonPath('data.next_day_id', $days[0]->id);
    }

    public function test_assistant_today_uses_the_rotation(): void
    {
        $days = $this->trainingDays();
        $this->makeSession($days[0], now()->subDays(2)->toDateTimeString());

        $this->bearer(['training:read'])->getJson('/api/v1/assistant/today')
            ->assertOk()->assertJsonPath('source', 'rotation')->assertJsonPath('day.id', $days[1]->id);
    }

    // ---- durations --------------------------------------------------------

    public function test_a_session_closed_hours_later_ends_at_its_last_set(): void
    {
        $this->actingAs($this->user);
        $day = $this->trainingDays()->first();
        $started = $this->postJson('/api/workouts/start', ['routine_day_id' => $day->id])->assertCreated()->json('data');
        $set = $this->postJson("/api/workout-exercises/{$started['exercises'][0]['id']}/sets", [
            'set_number' => 1, 'set_type' => 'working', 'weight' => 20, 'weight_unit' => 'lb', 'repetitions' => 10, 'rir' => 2, 'completed' => true,
        ])->assertCreated()->json('set');

        // Started 8h ago, last set 7h ago: the app was simply left open.
        WorkoutSession::whereKey($started['id'])->update(['started_at' => now()->subHours(8)]);
        DB::table('workout_sets')->where('id', $set['id'])->update(['completed_at' => now()->subHours(7)]);

        $finished = $this->postJson("/api/workouts/{$started['id']}/finish")->assertOk()->json('data');
        $this->assertEqualsWithDelta(3900, $finished['duration_seconds'], 5, 'one hour plus the five minute tail');
    }

    public function test_a_normal_session_still_counts_until_it_is_closed(): void
    {
        $this->actingAs($this->user);
        $day = $this->trainingDays()->first();
        $started = $this->postJson('/api/workouts/start', ['routine_day_id' => $day->id])->assertCreated()->json('data');
        $this->postJson("/api/workout-exercises/{$started['exercises'][0]['id']}/sets", [
            'set_number' => 1, 'set_type' => 'working', 'weight' => 20, 'weight_unit' => 'lb', 'repetitions' => 10, 'rir' => 2, 'completed' => true,
        ])->assertCreated();
        WorkoutSession::whereKey($started['id'])->update(['started_at' => now()->subMinutes(50)]);

        $finished = $this->postJson("/api/workouts/{$started['id']}/finish")->assertOk()->json('data');
        $this->assertEqualsWithDelta(3000, $finished['duration_seconds'], 5);
    }

    // ---- notes and summary ------------------------------------------------

    public function test_assistant_can_leave_notes_that_show_in_the_app(): void
    {
        $this->bearer(['training:read'])->postJson('/api/v1/assistant/notes', ['body' => 'Hoy sin hombro.'])->assertForbidden();

        $this->bearer(['notes:write'])->postJson('/api/v1/assistant/notes', ['body' => 'Mañana toca Upper B, empieza ligero.', 'kind' => 'summary'])
            ->assertCreated()->assertJsonPath('data.kind', 'summary');
        $this->bearer(['notes:write'])->postJson('/api/v1/assistant/notes', ['body' => 'x', 'kind' => 'change'])->assertUnprocessable();

        $this->actingAs($this->user)->getJson('/api/coach-notes')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.body', 'Mañana toca Upper B, empieza ligero.');
        $this->actingAs($this->user)->getJson('/api/dashboard')->assertJsonCount(1, 'coach_notes');
    }

    public function test_day_summary_collects_sessions_notes_and_next_session(): void
    {
        $day = $this->trainingDays()->first();
        $this->makeSession($day, now()->setTime(8, 0)->toDateTimeString());
        AssistantNote::create(['user_id' => $this->user->id, 'kind' => 'note', 'for_date' => now()->toDateString(), 'body' => 'Dormí poco.']);

        $this->bearer(['training:read'])->getJson('/api/v1/assistant/summary')
            ->assertOk()->assertJsonCount(1, 'sessions')->assertJsonCount(1, 'notes')
            ->assertJsonStructure(['date', 'sessions', 'notes', 'next_session' => ['routine_day_id', 'name', 'exercises']]);
    }

    // ---- routine changes --------------------------------------------------

    public function test_assistant_adjusts_a_slot_and_the_change_is_logged_for_the_athlete(): void
    {
        $slot = RoutineExercise::whereHas('routineDay', fn ($q) => $q->where('day_type', 'training'))->orderBy('id')->firstOrFail();
        $url = "/api/v1/assistant/routine/exercises/{$slot->id}";

        $this->bearer(['training:read', 'notes:write'])->putJson($url, ['target_sets' => 2, 'reason' => 'Molestia de hombro'])->assertForbidden();
        $this->bearer(['routine:write'])->putJson($url, ['target_sets' => 2])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->bearer(['routine:write'])->putJson($url, ['minimum_reps' => 12, 'maximum_reps' => 8, 'reason' => 'prueba'])->assertUnprocessable()->assertJsonValidationErrors('maximum_reps');

        $this->bearer(['routine:write'])->putJson($url, ['target_sets' => 2, 'rest_seconds' => 90, 'reason' => 'Semana de regreso: menos volumen'])->assertOk();
        $this->assertSame(2, $slot->fresh()->target_sets);

        $note = AssistantNote::where('kind', 'change')->firstOrFail();
        $this->assertStringContainsString('series', $note->body);
        $this->assertStringContainsString('Semana de regreso', $note->body);
        $this->assertSame('update', $note->payload_json['action']);
        $this->assertDatabaseHas('assistant_activity_logs', ['ability' => 'routine:write']);

        // Sending the same values again changes nothing and logs nothing new.
        $this->bearer(['routine:write'])->putJson($url, ['target_sets' => 2, 'reason' => 'otra vez'])->assertOk();
        $this->assertSame(1, AssistantNote::where('kind', 'change')->count());
    }

    public function test_assistant_can_swap_the_exercise_of_a_slot(): void
    {
        $slot = RoutineExercise::whereHas('routineDay', fn ($q) => $q->where('day_type', 'training'))->orderBy('id')->firstOrFail();
        $replacement = Exercise::where('user_id', $this->user->id)->where('id', '!=', $slot->exercise_id)->where('is_active', true)->firstOrFail();

        $this->bearer(['routine:write'])->putJson("/api/v1/assistant/routine/exercises/{$slot->id}", ['exercise_id' => $replacement->id, 'reason' => 'Máquina ocupada toda la semana'])->assertOk();

        $this->assertSame($replacement->id, $slot->fresh()->exercise_id);
        $this->assertStringContainsString($replacement->name, AssistantNote::where('kind', 'change')->firstOrFail()->body);
    }

    public function test_assistant_can_add_and_remove_slots_and_never_touch_other_users(): void
    {
        $day = $this->trainingDays()->first();
        $exercise = Exercise::where('user_id', $this->user->id)->where('is_active', true)
            ->whereNotIn('id', $day->exercises()->pluck('exercise_id'))->firstOrFail();

        $created = $this->bearer(['routine:write'])->postJson("/api/v1/assistant/routine/days/{$day->id}/exercises", [
            'exercise_id' => $exercise->id, 'target_sets' => 2, 'minimum_reps' => 10, 'maximum_reps' => 15, 'reason' => 'Más volumen de hombro',
        ])->assertCreated()->json('data');

        $this->bearer(['routine:write'])->deleteJson("/api/v1/assistant/routine/exercises/{$created['id']}", ['reason' => 'Ya no hace falta'])->assertOk();
        $this->assertDatabaseMissing('routine_exercises', ['id' => $created['id']]);
        $this->assertSame(2, AssistantNote::where('kind', 'change')->count());

        $other = User::factory()->create();
        $foreign = $other->routines()->create(['name' => 'Otra', 'is_active' => true]);
        $foreignDay = $foreign->days()->create(['name' => 'X', 'weekday' => 1, 'position' => 1, 'day_type' => 'training']);
        $this->bearer(['routine:write'])->postJson("/api/v1/assistant/routine/days/{$foreignDay->id}/exercises", [
            'exercise_id' => $exercise->id, 'target_sets' => 2, 'reason' => 'intento ajeno',
        ])->assertForbidden();
    }

    // ---- weekly volume ----------------------------------------------------

    public function test_weekly_volume_counts_only_direct_working_sets_per_muscle(): void
    {
        $this->actingAs($this->user);
        $day = $this->trainingDays()->first();
        $started = $this->postJson('/api/workouts/start', ['routine_day_id' => $day->id])->assertCreated()->json('data');
        $first = $started['exercises'][0];
        foreach ([['warmup', 10], ['working', 10], ['working', 9]] as $index => [$type, $reps]) {
            $this->postJson("/api/workout-exercises/{$first['id']}/sets", [
                'set_number' => $index + 1, 'set_type' => $type, 'weight' => 20, 'weight_unit' => 'lb', 'repetitions' => $reps, 'rir' => 2, 'completed' => true,
            ])->assertCreated();
        }
        $this->postJson("/api/workouts/{$started['id']}/finish")->assertOk();

        $response = $this->getJson('/api/progress/weekly-volume?weeks=2')->assertOk()
            ->assertJsonPath('target.min', 10)->assertJsonCount(2, 'weeks');
        $muscleName = $first['performed_exercise']['muscle_groups'][0]['name'];
        $muscle = collect($response->json('muscles'))->firstWhere('name', $muscleName);

        $this->assertNotNull($muscle);
        $this->assertSame(2, $muscle['weeks'][1], 'two working sets this week, the warm-up is excluded');
        $this->assertSame(0, $muscle['weeks'][0]);
        $this->assertGreaterThan(0, $muscle['planned']);
    }
}
