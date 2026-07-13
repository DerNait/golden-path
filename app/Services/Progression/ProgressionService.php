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
        $exposures = $this->exposures($user,$exercise);
        if ($exposures->count() < 2 || ! $routineExercise) {
            return $this->result(
                RecommendationType::Calibrate,
                RecommendationConfidence::Low,
                'Calibrando: registra al menos dos exposiciones validas antes de ajustar la carga.',
                $routineExercise?->target_weight,
            );
        }

        $latest = $exposures->first();
        $workingSets = $this->workingSets($latest);
        if ($workingSets->isEmpty()) {
            return $this->result(RecommendationType::ManualReview,RecommendationConfidence::Low,'Faltan series efectivas para interpretar el rendimiento.');
        }

        $targetSets = max(1,(int) $routineExercise->target_sets);
        if ($workingSets->count() < $targetSets) {
            return $this->result(RecommendationType::Maintain,RecommendationConfidence::Low,'Faltaron series planificadas. Conserva el objetivo y completa la exposicion antes de ajustar la progresion.');
        }

        $sets = $workingSets->take($targetSets)->values();
        if ($exercise->metric_type === 'duration') {
            return $this->result(RecommendationType::ManualReview,RecommendationConfidence::Low,'La progresion por duracion requiere una meta especifica y no debe interpretarse como peso por repeticiones.');
        }

        $weightedExercise = $exercise->metric_type === 'weight_reps';
        if ($weightedExercise && ! $this->hasComparableWorkingLoad($sets)) {
            return $this->result(RecommendationType::ManualReview,RecommendationConfidence::Low,'Las series efectivas no tienen una carga y unidad consistentes. Revisa el registro antes de ajustar la progresion.');
        }

        $weight = $weightedExercise ? (float) $sets->first()->weight : null;
        $repetitions = $sets->pluck('repetitions')->map(fn ($value) => (int) $value);
        $averageRir = $sets->whereNotNull('rir')->avg('rir');
        $allRirRecorded = $sets->every(fn ($set) => $set->rir !== null);
        $targetPerSet = (int) ($routineExercise->progression_target_reps ?: $routineExercise->maximum_reps);
        $mastered = $repetitions->every(fn (int $reps) => $reps >= $targetPerSet);

        if (trim((string) $latest->session->discomfort_notes) !== '') {
            return $this->result(RecommendationType::ManualReview,RecommendationConfidence::Low,'Registraste molestias o comentarios fisicos. Revisa tecnica y tolerancia antes de cambiar la carga.',$weight);
        }

        if ($latest->session->is_atypical) {
            return $this->result(RecommendationType::Maintain,RecommendationConfidence::Low,'La ultima sesion fue atipica. Conserva la carga y reevalua en una sesion normal.',$weight);
        }

        $recoveryConcern = $this->recoveryConcern($user,$latest);

        if ($mastered) {
            if ($recoveryConcern) {
                return $this->result(RecommendationType::Maintain,RecommendationConfidence::Low,$recoveryConcern.' Repite el objetivo en una sesion con mejor recuperacion antes de subir.',$weight);
            }
            if ($sets->whereNotNull('technique_rating')->contains(fn ($set) => $set->technique_rating < 3)) {
                return $this->result(RecommendationType::Maintain,RecommendationConfidence::Low,'Completaste las repeticiones, pero registraste tecnica baja. Conserva la carga hasta repetirlas con buena ejecucion.',$weight);
            }
            if (! $allRirRecorded) {
                return $this->result(RecommendationType::Maintain,RecommendationConfidence::Low,'Completaste el objetivo, pero falta RIR en una o mas series. Repite la carga con RIR completo antes de subir.',$weight);
            }
            if ($averageRir < 1) {
                return $this->result(RecommendationType::Maintain,RecommendationConfidence::Medium,'Completaste el objetivo muy cerca del fallo. Conserva la carga hasta lograrlo con al menos 1 RIR promedio.',$weight);
            }
            if (! $weightedExercise) {
                return $this->result(RecommendationType::ManualReview,RecommendationConfidence::Medium,'Dominaste el rango del ejercicio con peso corporal. Revisa una variante mas dificil o una carga externa apropiada.',$weight);
            }

            $increment = (float) ($routineExercise->weight_increment ?: $exercise->default_increment ?: 1);
            return $this->result(
                RecommendationType::IncreaseWeight,
                RecommendationConfidence::High,
                sprintf('Completaste el objetivo maximo en todas las series con RIR promedio de %s. Sube la carga usando el incremento configurado.',number_format($averageRir,1)),
                $weight,
                $this->roundToIncrement($weight + $increment,$increment),
            );
        }

        if ($this->hasStrongDropWithShortRest($sets,(int) $routineExercise->rest_seconds)) {
            return $this->result(RecommendationType::IncreaseRest,RecommendationConfidence::Medium,'La caida entre series coincide con descansos cortos. Anade entre 15 y 30 segundos antes de reducir la carga.',$weight);
        }

        $minimum = (int) $routineExercise->minimum_reps;
        $belowRange = $repetitions->filter(fn (int $reps) => $reps < $minimum)->count() > $sets->count() / 2;
        if ($belowRange) {
            if ($recoveryConcern) {
                return $this->result(RecommendationType::Maintain,RecommendationConfidence::Low,$recoveryConcern.' Una exposicion con recuperacion baja no basta para reducir peso.',$weight);
            }

            $previous = $exposures->get(1);
            $previousSets = $previous ? $this->workingSets($previous)->take($targetSets)->values() : collect();
            $previousBelow = $previousSets->count() === $targetSets
                && (! $weightedExercise || $this->sameWorkingLoad($sets,$previousSets))
                && $previousSets->filter(fn ($set) => $set->repetitions < $minimum)->count() > $previousSets->count() / 2;

            if ($previousBelow) {
                return $this->result(RecommendationType::ReduceWeight,RecommendationConfidence::Medium,'Dos exposiciones comparables quedaron bajo el rango. Prueba una reduccion conservadora de 5% a 7%.',$weight,$weightedExercise ? round($weight * 0.94,2) : null);
            }

            return $this->result(RecommendationType::Maintain,RecommendationConfidence::Medium,'Una sola exposicion comparable bajo el rango no justifica reducir peso. Repite la carga y revisa sueno, energia y tecnica.',$weight);
        }

        $stagnation = $this->stagnation->detect($exposures,$exercise->metric_type);
        if ($stagnation) {
            if ($this->recoveryConcernCount($user,$exposures->take(4)) >= 2) {
                return $this->result(RecommendationType::PossibleDeload,RecommendationConfidence::Medium,$stagnation['reason'].' Tambien hay senales repetidas de recuperacion baja; considera una descarga o una semana de menor volumen.',$weight);
            }

            return $this->result(RecommendationType::ManualReview,RecommendationConfidence::Medium,$stagnation['reason'].' No hay suficientes senales de fatiga para indicar una descarga automatica; revisa recuperacion y tecnica.',$weight);
        }

        $previous = $exposures->get(1);
        if ($previous && $this->sameRepetitionsWithBetterRir($latest,$previous,$weightedExercise)) {
            return $this->result(RecommendationType::Maintain,RecommendationConfidence::Medium,'Mantuviste las repeticiones con menor esfuerzo percibido. Esto cuenta como mejora de control; conserva el peso una sesion mas.',$weight);
        }

        $currentTotal = $repetitions->sum();
        $maximumTotal = $targetSets * (int) $routineExercise->maximum_reps;
        if ($currentTotal >= $maximumTotal) {
            return $this->result(RecommendationType::Maintain,RecommendationConfidence::Medium,'Alcanzaste el total maximo, pero aun falta dominar la meta en cada serie. Conserva la carga y distribuye mejor las repeticiones.',$weight,null,$maximumTotal);
        }

        $nextStep = $allRirRecorded && $averageRir >= 2 ? 2 : 1;
        $existingTarget = (int) ($routineExercise->progression_target_total_reps ?? 0);
        $suggestedTotal = min($maximumTotal,max($existingTarget,$currentTotal + $nextStep));
        $confidence = $allRirRecorded ? RecommendationConfidence::Medium : RecommendationConfidence::Low;
        $reason = $allRirRecorded
            ? "Manten el peso actual. Busca al menos {$suggestedTotal} repeticiones totales manteniendo el RIR entre 1 y 2."
            : "Manten el peso actual y busca {$suggestedTotal} repeticiones totales. Registra el RIR en todas las series para mejorar la confianza.";

        return $this->result(RecommendationType::IncreaseRepetitions,$confidence,$reason,$weight,null,$suggestedTotal);
    }

    private function exposures(User $user, Exercise $exercise): Collection
    {
        return WorkoutExercise::query()
            ->where('performed_exercise_id',$exercise->id)
            ->whereHas('session',fn ($query) => $query->where('user_id',$user->id)->whereIn('status',['completed','partial']))
            ->whereHas('sets',fn ($query) => $query->where('completed',true)->where('set_type','working'))
            ->with(['sets','session'])
            ->latest('id')
            ->limit(6)
            ->get();
    }

    private function workingSets(WorkoutExercise $exercise): Collection
    {
        return $exercise->sets->where('completed',true)->where('set_type','working')->sortBy('set_number')->values();
    }

    private function hasStrongDropWithShortRest(Collection $sets, int $plannedRest): bool
    {
        $first = (int) $sets->first()->repetitions;
        $last = (int) $sets->last()->repetitions;
        $averageRest = $sets->whereNotNull('rest_seconds_actual')->avg('rest_seconds_actual');

        return $first > 0 && $last <= $first * 0.65 && $averageRest !== null && $averageRest < $plannedRest;
    }

    private function sameRepetitionsWithBetterRir(WorkoutExercise $latest, WorkoutExercise $previous, bool $weightedExercise): bool
    {
        $latestSets = $this->workingSets($latest);
        $previousSets = $this->workingSets($previous);

        return $latestSets->sum('repetitions') === $previousSets->sum('repetitions')
            && (! $weightedExercise || $this->sameWorkingLoad($latestSets,$previousSets))
            && $latestSets->whereNotNull('rir')->isNotEmpty()
            && $previousSets->whereNotNull('rir')->isNotEmpty()
            && $latestSets->whereNotNull('rir')->avg('rir') > $previousSets->whereNotNull('rir')->avg('rir')
            && ($latestSets->whereNotNull('technique_rating')->avg('technique_rating') ?? 5) >= ($previousSets->whereNotNull('technique_rating')->avg('technique_rating') ?? 0);
    }

    private function hasComparableWorkingLoad(Collection $sets): bool
    {
        return $sets->every(fn ($set) => $set->weight !== null && $set->weight_unit !== null)
            && $sets->pluck('weight_unit')->unique()->count() === 1
            && $sets->pluck('weight')->map(fn ($weight) => number_format((float) $weight,2,'.',''))->unique()->count() === 1;
    }

    private function sameWorkingLoad(Collection $current, Collection $previous): bool
    {
        if (! $this->hasComparableWorkingLoad($current) || ! $this->hasComparableWorkingLoad($previous)) return false;

        return $current->first()->weight_unit === $previous->first()->weight_unit
            && (float) $current->first()->weight === (float) $previous->first()->weight;
    }

    private function recoveryConcern(User $user, WorkoutExercise $exposure): ?string
    {
        $session = $exposure->session;
        $signals = [];
        if ($session->energy_level !== null && $session->energy_level <= 2) $signals[] = 'energia baja';
        if ($session->motivation_level !== null && $session->motivation_level <= 2) $signals[] = 'motivacion baja';
        if ($session->session_difficulty !== null && $session->session_difficulty >= 5) $signals[] = 'dificultad maxima';

        if ($session->sleep_hours !== null) {
            $user->loadMissing('profile');
            $sleepGoal = (float) ($user->profile?->sleep_goal_hours ?: 7);
            if ((float) $session->sleep_hours < max(5.0,$sleepGoal - 1.0)) $signals[] = 'sueno por debajo de tu meta';
        }

        return $signals ? 'La sesion tuvo '.implode(', ',$signals).'.' : null;
    }

    private function recoveryConcernCount(User $user, Collection $exposures): int
    {
        return $exposures->filter(fn (WorkoutExercise $exposure) => $this->recoveryConcern($user,$exposure) !== null
            || trim((string) $exposure->session->discomfort_notes) !== '')->count();
    }

    private function roundToIncrement(float $value, float $increment): float
    {
        return round(round($value / $increment) * $increment,2);
    }

    private function result(RecommendationType $type, RecommendationConfidence $confidence, string $reason, ?float $currentWeight = null, ?float $suggestedWeight = null, ?int $suggestedRepetitions = null): array
    {
        return [
            'recommendation_type'=>$type->value,
            'confidence'=>$confidence->value,
            'reason'=>$reason,
            'current_weight'=>$currentWeight,
            'suggested_weight'=>$suggestedWeight,
            'suggested_total_repetitions'=>$suggestedRepetitions,
        ];
    }
}
