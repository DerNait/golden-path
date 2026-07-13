<?php

namespace App\Services\Progression;

use App\Enums\RecommendationConfidence;
use App\Enums\RecommendationType;
use App\Models\Exercise;
use App\Models\RoutineExercise;
use App\Models\User;
use App\Models\WorkoutExercise;
use Illuminate\Support\Collection;

class ProgressionService
{
    public function __construct(private readonly StagnationDetectionService $stagnation) {}

    public function recommend(User $user, Exercise $exercise, ?RoutineExercise $routineExercise): array
    {
        $exposures = $this->exposures($user, $exercise);
        if ($exposures->count() < 2 || ! $routineExercise) {
            return $this->result(
                RecommendationType::Calibrate,
                RecommendationConfidence::Low,
                'Calibrando: registra al menos dos exposiciones validas antes de ajustar la carga.',
                $routineExercise?->target_weight,
            );
        }

        $latest = $exposures->first();
        $sets = $this->workingSets($latest);
        if ($sets->isEmpty()) {
            return $this->result(RecommendationType::ManualReview, RecommendationConfidence::Low, 'Faltan series efectivas para interpretar el rendimiento.');
        }

        $weight = (float) $sets->first()->weight;
        $repetitions = $sets->pluck('repetitions')->map(fn ($value) => (int) $value);
        $averageRir = $sets->whereNotNull('rir')->avg('rir');
        $allSetsPresent = $sets->count() >= $routineExercise->target_sets;
        $mastered = $allSetsPresent && $repetitions->every(fn (int $reps) => $reps >= $routineExercise->progression_target_reps);

        if ($mastered && ($averageRir === null || $averageRir >= 1) && ! $latest->session->is_atypical) {
            $increment = (float) ($routineExercise->weight_increment ?: $exercise->default_increment ?: 1);
            return $this->result(
                RecommendationType::IncreaseWeight,
                RecommendationConfidence::High,
                sprintf('Completaste el objetivo maximo en todas las series%s. Sube la carga usando el incremento configurado.', $averageRir !== null ? ' con RIR promedio de '.number_format($averageRir, 1) : ''),
                $weight,
                $this->roundToIncrement($weight + $increment, $increment),
            );
        }

        if ($latest->session->is_atypical) {
            return $this->result(RecommendationType::Maintain, RecommendationConfidence::Low, 'La ultima sesion fue atipica. Conserva la carga y reevalua en una sesion normal.', $weight);
        }

        if ($this->hasStrongDropWithShortRest($sets, (int) $routineExercise->rest_seconds)) {
            return $this->result(RecommendationType::IncreaseRest, RecommendationConfidence::Medium, 'La caida entre series coincide con descansos cortos. Anade entre 15 y 30 segundos antes de reducir la carga.', $weight);
        }

        $minimum = (int) $routineExercise->minimum_reps;
        $belowRange = $repetitions->filter(fn (int $reps) => $reps < $minimum)->count() > $sets->count() / 2;
        if ($belowRange) {
            $previous = $exposures->get(1);
            $previousSets = $previous ? $this->workingSets($previous) : collect();
            $previousBelow = $previousSets->isNotEmpty()
                && $previousSets->filter(fn ($set) => $set->repetitions < $minimum)->count() > $previousSets->count() / 2;

            if ($previousBelow) {
                if ($this->hasStrongDropWithShortRest($sets, (int) $routineExercise->rest_seconds)) {
                    return $this->result(RecommendationType::IncreaseRest, RecommendationConfidence::Medium, 'La caida entre series coincide con descansos cortos. Anade entre 15 y 30 segundos antes de reducir la carga.', $weight);
                }
                return $this->result(RecommendationType::ReduceWeight, RecommendationConfidence::Medium, 'Dos exposiciones consecutivas quedaron bajo el rango. Prueba una reduccion conservadora de 5% a 7%.', $weight, round($weight * 0.94, 2));
            }

            return $this->result(RecommendationType::Maintain, RecommendationConfidence::Medium, 'Una sola exposicion bajo el rango no justifica reducir peso. Repite la carga y revisa sueno, energia y tecnica.', $weight);
        }

        $stagnation = $this->stagnation->detect($exposures);
        if ($stagnation) {
            return $this->result(RecommendationType::PossibleDeload, RecommendationConfidence::Medium, $stagnation['reason'].' '.$stagnation['action'], $weight);
        }

        $previous = $exposures->get(1);
        if ($previous && $this->sameRepetitionsWithBetterRir($latest, $previous)) {
            return $this->result(RecommendationType::Maintain, RecommendationConfidence::Medium, 'Mantuviste las repeticiones con menor esfuerzo percibido. Esto cuenta como mejora de control; conserva el peso una sesion mas.', $weight);
        }

        $currentTotal = $repetitions->sum();
        $maximumTotal = $routineExercise->target_sets * $routineExercise->maximum_reps;
        $suggestedTotal = min($maximumTotal, $currentTotal + ($averageRir !== null && $averageRir >= 2 ? 2 : 1));
        return $this->result(
            RecommendationType::IncreaseRepetitions,
            RecommendationConfidence::Medium,
            "Mantén el peso actual. Busca al menos {$suggestedTotal} repeticiones totales manteniendo el RIR entre 1 y 2.",
            $weight,
            null,
            $suggestedTotal,
        );
    }

    private function exposures(User $user, Exercise $exercise): Collection
    {
        return WorkoutExercise::query()
            ->where('performed_exercise_id', $exercise->id)
            ->whereHas('session', fn ($query) => $query->where('user_id', $user->id)->whereIn('status', ['completed','partial']))
            ->whereHas('sets', fn ($query) => $query->where('completed', true)->where('set_type', 'working'))
            ->with(['sets', 'session'])
            ->latest('id')
            ->limit(6)
            ->get();
    }

    private function workingSets(WorkoutExercise $exercise): Collection
    {
        return $exercise->sets->where('completed', true)->where('set_type', 'working')->values();
    }

    private function hasStrongDropWithShortRest(Collection $sets, int $plannedRest): bool
    {
        $first = (int) $sets->first()->repetitions;
        $last = (int) $sets->last()->repetitions;
        $averageRest = $sets->whereNotNull('rest_seconds_actual')->avg('rest_seconds_actual');
        return $first > 0 && $last <= $first * 0.65 && $averageRest !== null && $averageRest < $plannedRest;
    }

    private function sameRepetitionsWithBetterRir(WorkoutExercise $latest, WorkoutExercise $previous): bool
    {
        $latestSets = $this->workingSets($latest);
        $previousSets = $this->workingSets($previous);
        return $latestSets->sum('repetitions') === $previousSets->sum('repetitions')
            && $latestSets->whereNotNull('rir')->isNotEmpty()
            && $previousSets->whereNotNull('rir')->isNotEmpty()
            && $latestSets->whereNotNull('rir')->avg('rir') > $previousSets->whereNotNull('rir')->avg('rir')
            && ($latestSets->whereNotNull('technique_rating')->avg('technique_rating') ?? 5) >= ($previousSets->whereNotNull('technique_rating')->avg('technique_rating') ?? 0);
    }

    private function roundToIncrement(float $value, float $increment): float
    {
        return round(round($value / $increment) * $increment, 2);
    }

    private function result(RecommendationType $type, RecommendationConfidence $confidence, string $reason, ?float $currentWeight = null, ?float $suggestedWeight = null, ?int $suggestedRepetitions = null): array
    {
        return [
            'recommendation_type'=>$type->value,'confidence'=>$confidence->value,'reason'=>$reason,
            'current_weight'=>$currentWeight,'suggested_weight'=>$suggestedWeight,
            'suggested_total_repetitions'=>$suggestedRepetitions,
        ];
    }
}
