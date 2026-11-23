<?php

namespace App\Http\Controllers\Api\Assistant;

use App\Http\Controllers\Controller;
use App\Models\AssistantNote;
use App\Services\Workouts\RotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * One-call picture of a day: what was trained, how it felt, what the assistant
 * wrote or changed, and what is next in the rotation.
 */
class SummaryController extends Controller
{
    public function __construct(private readonly RotationService $rotation) {}

    public function show(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']])['date'] ?? now()->toDateString();
        $user = $request->user();

        $sessions = $user->workouts()->whereDate('started_at', $date)
            ->with(['exercises.performedExercise', 'exercises.sets'])->orderBy('started_at')->get()
            ->map(fn ($session) => [
                'id' => $session->id, 'name' => $session->name, 'status' => $session->status,
                'minutes' => $session->duration_seconds ? (int) round($session->duration_seconds / 60) : null,
                'working_sets' => $session->working_sets_count, 'volume' => $session->total_volume !== null ? (float) $session->total_volume : null,
                'check_in' => array_filter([
                    'sleep_hours' => $session->sleep_hours !== null ? (float) $session->sleep_hours : null,
                    'energy' => $session->energy_level, 'motivation' => $session->motivation_level,
                    'difficulty' => $session->session_difficulty, 'satisfaction' => $session->session_satisfaction,
                    'discomfort' => $session->discomfort_notes, 'atypical' => $session->is_atypical ? ($session->atypical_reason ?: true) : null,
                    'notes' => $session->notes,
                ], fn ($value) => $value !== null && $value !== ''),
                'exercises' => $session->exercises->map(fn ($exercise) => [
                    'exercise' => $exercise->performedExercise?->name,
                    'substituted' => (bool) $exercise->was_substituted,
                    'sets' => $exercise->sets->where('completed', true)->map(fn ($set) => trim(($set->set_type === 'warmup' ? 'cal ' : '')
                        .($set->weight !== null ? (float) $set->weight.($set->weight_unit ?? '').' x ' : '')
                        .($set->repetitions ?? ($set->duration_seconds !== null ? $set->duration_seconds.'s' : '-'))
                        .($set->rir !== null ? ' @'.$set->rir : '')))->values(),
                ])->values(),
            ])->values();

        $next = $this->rotation->next($user);
        $next?->load('exercises.exercise');

        return response()->json([
            'date' => $date,
            'sessions' => $sessions,
            'notes' => AssistantNote::where('user_id', $user->id)->where('for_date', $date)->orderBy('id')->get()->map->toBrief()->values(),
            'next_session' => $next ? [
                'routine_day_id' => $next->id, 'name' => $next->name,
                'exercises' => $next->exercises->map(fn ($slot) => [
                    'routine_exercise_id' => $slot->id, 'exercise_id' => $slot->exercise_id, 'name' => $slot->exercise->name,
                    'sets' => $slot->target_sets, 'reps' => $slot->minimum_reps.'-'.$slot->maximum_reps, 'priority' => $slot->priority,
                ])->values(),
            ] : null,
        ]);
    }
}
