<?php

namespace App\Http\Controllers\Api\Assistant;

use App\Enums\RecommendationStatus;
use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\PersonalRecord;
use App\Models\ProgressionRecommendation;
use App\Models\WorkoutExercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only endpoints for the AI assistant. Responses are summarized and
 * scoped to the authenticated token's user; no internal snapshots, models or
 * credentials are exposed.
 */
class AssistantController extends Controller
{
    public function today(Request $request): JsonResponse
    {
        $user = $request->user();
        $routine = $user->routines()->where('is_active', true)->first();
        $day = $routine?->days()->where('weekday', now()->dayOfWeekIso)
            ->with('exercises.exercise.muscleGroups')->first();

        if (! $day) {
            return response()->json(['date' => now()->toDateString(), 'is_training_day' => false, 'day' => null, 'exercises' => []]);
        }

        $exercises = $day->exercises->where('exercise.metric_type', '!=', null)->map(function ($re) use ($user) {
            $pending = ProgressionRecommendation::where('user_id', $user->id)
                ->where('exercise_id', $re->exercise_id)->where('status', 'pending')->latest()->first();

            return [
                'exercise' => $this->exerciseBrief($re->exercise),
                'priority' => $re->priority,
                'target' => [
                    'sets' => $re->target_sets, 'min_reps' => $re->minimum_reps, 'max_reps' => $re->maximum_reps,
                    'progression_target_total_reps' => $re->progression_target_total_reps,
                    'target_weight' => $this->decimal($re->target_weight), 'weight_unit' => $re->weight_unit,
                    'rir_min' => $re->target_rir_min, 'rir_max' => $re->target_rir_max, 'rest_seconds' => $re->rest_seconds,
                ],
                'last_performance' => $this->lastPerformance($user->id, $re->exercise_id),
                'pending_recommendation' => $pending ? $this->recommendationBrief($pending) : null,
            ];
        })->values();

        return response()->json([
            'date' => now()->toDateString(), 'is_training_day' => $day->day_type === 'training',
            'day' => ['name' => $day->name, 'type' => $day->day_type], 'exercises' => $exercises,
        ]);
    }

    public function recentWorkouts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'since' => ['nullable', 'date'],
        ]);
        $limit = $data['limit'] ?? 10;

        $sessions = $request->user()->workouts()
            ->whereIn('status', ['completed', 'partial'])
            ->when($data['since'] ?? null, fn ($q, $since) => $q->whereDate('started_at', '>=', $since))
            ->with(['exercises.performedExercise:id,name', 'exercises.sets', 'routineDay:id,name'])
            ->latest('started_at')->limit($limit)->get();

        $payload = $sessions->map(fn ($s) => [
            'id' => $s->id, 'day' => $s->routineDay?->name, 'status' => $s->status,
            'started_at' => optional($s->started_at)->toIso8601String(),
            'duration_seconds' => $s->duration_seconds, 'total_volume' => $this->decimal($s->total_volume),
            'exercises' => $s->exercises->map(fn ($we) => [
                'exercise' => ['id' => $we->performed_exercise_id, 'name' => $we->performedExercise?->name],
                'was_substituted' => (bool) $we->was_substituted,
                'working_sets' => $we->sets->where('completed', true)->where('set_type', 'working')->sortBy('set_number')
                    ->map(fn ($set) => $this->setBrief($set))->values(),
            ])->values(),
        ]);

        return response()->json(['data' => $payload]);
    }

    public function exerciseHistory(Request $request, Exercise $exercise): JsonResponse
    {
        $this->authorize('view', $exercise);

        $exposures = WorkoutExercise::where('performed_exercise_id', $exercise->id)
            ->whereHas('session', fn ($q) => $q->where('user_id', $request->user()->id)->whereIn('status', ['completed', 'partial']))
            ->with(['sets' => fn ($q) => $q->where('completed', true)->where('set_type', 'working'), 'session:id,started_at'])
            ->oldest('id')->get();

        $series = $exposures->map(function ($exposure) {
            $sets = $exposure->sets;
            $best = $sets->sortByDesc(fn ($set) => $set->volume)->first();

            return [
                'date' => optional($exposure->session->started_at)->toDateString(),
                'max_weight' => (float) $sets->max('weight'),
                'best_set' => $best ? ['weight' => $this->decimal($best->weight), 'reps' => (int) $best->repetitions, 'volume' => (float) $best->volume] : null,
                'total_repetitions' => (int) $sets->sum('repetitions'),
                'volume' => (float) $sets->sum(fn ($set) => $set->volume),
                'average_rir' => $sets->whereNotNull('rir')->avg('rir'),
                'estimated_one_rep_max' => (float) $sets->max('estimated_one_rep_max'),
            ];
        })->values();

        return response()->json([
            'exercise' => $this->exerciseBrief($exercise->loadMissing('muscleGroups')),
            'calibration' => ['exposures' => $exposures->count(), 'complete' => $exposures->count() >= 2],
            'series' => $series,
            'records' => PersonalRecord::where('user_id', $request->user()->id)->where('exercise_id', $exercise->id)
                ->get(['record_type', 'value', 'weight_unit', 'achieved_at']),
        ]);
    }

    public function trainingContext(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile;
        $phase = $user->trainingPhases()->where('status', 'active')->latest('starts_on')->first();
        $measurement = $user->bodyMeasurements()->latest('recorded_on')->latest('id')->first();

        return response()->json([
            'profile' => $profile ? [
                'age' => $profile->age, 'height_cm' => $this->decimal($profile->height_cm),
                'biological_sex' => $profile->biological_sex, 'objective' => $profile->objective,
                'experience_level' => $profile->experience_level, 'weekly_workout_goal' => $profile->weekly_workout_goal,
                'target_session_minutes' => $profile->target_session_minutes,
                'protein_goal_min_grams' => $profile->protein_goal_min_grams, 'protein_goal_max_grams' => $profile->protein_goal_max_grams,
                'sleep_goal_hours' => $this->decimal($profile->sleep_goal_hours),
                'current_body_weight_kg' => $this->decimal($profile->current_body_weight_kg),
                'preferred_body_weight_unit' => $profile->preferred_body_weight_unit,
            ] : null,
            'active_phase' => $phase ? [
                'name' => $phase->name, 'starts_on' => optional($phase->starts_on)->toDateString(),
                'ends_on' => optional($phase->ends_on)->toDateString(),
                'minimum_target_sessions' => $phase->minimum_target_sessions,
                'target_weight_min_kg' => $this->decimal($phase->target_weight_min_kg),
                'target_weight_max_kg' => $this->decimal($phase->target_weight_max_kg),
            ] : null,
            'latest_body_composition' => $measurement ? $this->compositionBrief($measurement) : null,
        ]);
    }

    public function bodyStats(Request $request): JsonResponse
    {
        $measurements = $request->user()->bodyMeasurements()->oldest('recorded_on')->get();
        $latest = $measurements->last();

        return response()->json([
            'latest' => $latest ? $this->compositionBrief($latest) : null,
            'trend' => $measurements->map(fn ($m) => [
                'recorded_on' => optional($m->recorded_on)->toDateString(),
                'body_weight_kg' => $this->decimal($m->body_weight_kg),
                'body_fat_percentage' => $this->decimal($m->body_fat_percentage),
                'skeletal_muscle_mass_kg' => $this->decimal($m->skeletal_muscle_mass_kg),
                'waist_cm' => $this->decimal($m->waist_cm),
            ])->values(),
        ]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:120', function (string $attribute, mixed $value, callable $fail): void {
                $allowed = array_column(RecommendationStatus::cases(), 'value');
                foreach (explode(',', (string) $value) as $part) {
                    $part = trim($part);
                    if ($part !== 'all' && ! in_array($part, $allowed, true)) {
                        $fail("Estado no valido: {$part}.");
                    }
                }
            }],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $status = $data['status'] ?? RecommendationStatus::Pending->value;
        $query = ProgressionRecommendation::where('user_id', $request->user()->id)
            ->with('exercise:id,name')->latest();

        if ($status !== 'all') {
            $query->whereIn('status', array_filter(array_map('trim', explode(',', $status))));
        }

        $recommendations = $query->limit($data['limit'] ?? 50)->get();

        return response()->json([
            'status' => $status,
            'data' => $recommendations->map(fn ($r) => array_merge($this->recommendationBrief($r), [
                'exercise' => ['id' => $r->exercise_id, 'name' => $r->exercise?->name],
            ]))->values(),
        ]);
    }

    private function exerciseBrief(Exercise $exercise): array
    {
        return [
            'id' => $exercise->id, 'name' => $exercise->name, 'metric_type' => $exercise->metric_type,
            'muscle_groups' => $exercise->relationLoaded('muscleGroups') ? $exercise->muscleGroups->pluck('name')->values() : [],
        ];
    }

    private function lastPerformance(int $userId, int $exerciseId): ?array
    {
        $we = WorkoutExercise::where('performed_exercise_id', $exerciseId)
            ->whereHas('session', fn ($q) => $q->where('user_id', $userId)->whereIn('status', ['completed', 'partial']))
            ->with(['sets', 'session:id,started_at'])->latest('id')->first();

        if (! $we) {
            return null;
        }

        return [
            'date' => optional($we->session->started_at)->toDateString(),
            'working_sets' => $we->sets->where('completed', true)->where('set_type', 'working')->sortBy('set_number')
                ->map(fn ($set) => $this->setBrief($set))->values(),
        ];
    }

    private function setBrief($set): array
    {
        return [
            'weight' => $this->decimal($set->weight), 'weight_unit' => $set->weight_unit,
            'repetitions' => $set->repetitions !== null ? (int) $set->repetitions : null,
            'rir' => $set->rir !== null ? (int) $set->rir : null, 'volume' => (float) $set->volume,
        ];
    }

    private function recommendationBrief(ProgressionRecommendation $r): array
    {
        return [
            'id' => $r->id, 'type' => $r->recommendation_type, 'confidence' => $r->confidence, 'reason' => $r->reason,
            'current_weight' => $this->decimal($r->current_weight), 'suggested_weight' => $this->decimal($r->suggested_weight),
            'suggested_total_repetitions' => $r->suggested_total_repetitions,
            'suggested_rep_distribution' => $r->suggested_rep_distribution, 'weight_unit' => $r->weight_unit,
            'source' => data_get($r->metadata_json, 'source', 'engine'),
            'status' => $r->status,
            'accepted_at' => optional($r->accepted_at)->toIso8601String(),
            'created_at' => optional($r->created_at)->toIso8601String(),
        ];
    }

    private function compositionBrief($m): array
    {
        return [
            'recorded_on' => optional($m->recorded_on)->toDateString(),
            'body_weight_kg' => $this->decimal($m->body_weight_kg), 'waist_cm' => $this->decimal($m->waist_cm),
            'body_fat_percentage' => $this->decimal($m->body_fat_percentage), 'fat_mass_kg' => $this->decimal($m->fat_mass_kg),
            'lean_mass_kg' => $this->decimal($m->lean_mass_kg), 'skeletal_muscle_mass_kg' => $this->decimal($m->skeletal_muscle_mass_kg),
            'total_body_water_kg' => $this->decimal($m->total_body_water_kg), 'visceral_fat_level' => $m->visceral_fat_level,
            'basal_metabolic_rate_kcal' => $m->basal_metabolic_rate_kcal,
        ];
    }

    private function decimal($value): ?float
    {
        return $value !== null ? (float) $value : null;
    }
}
