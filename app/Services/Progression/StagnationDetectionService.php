<?php

namespace App\Services\Progression;

use App\Models\WorkoutExercise;
use Illuminate\Support\Collection;

class StagnationDetectionService
{
    public function detect(Collection $exposures, string $metricType = 'weight_reps'): ?array
    {
        if ($exposures->count() < 4) {
            return null;
        }

        $metrics = $exposures->take(4)->map(function (WorkoutExercise $exposure): array {
            $sets = $exposure->sets->where('completed', true)->where('set_type', 'working');
            return [
                'weight' => (float) $sets->max('weight'),
                'repetitions' => (int) $sets->sum('repetitions'),
                'best_set' => (int) $sets->max('repetitions'),
                'volume' => (float) $sets->sum(fn ($set) => $set->volume),
                'estimated_one_rep_max' => (float) $sets->max('estimated_one_rep_max'),
                'rir' => $sets->whereNotNull('rir')->avg('rir'),
                'weight_unit' => $sets->whereNotNull('weight_unit')->pluck('weight_unit')->unique()->count() === 1
                    ? $sets->whereNotNull('weight_unit')->first()->weight_unit
                    : null,
            ];
        })->values();

        if ($metricType === 'weight_reps' && $metrics->pluck('weight_unit')->filter()->unique()->count() > 1) {
            return null;
        }

        $oldest = $metrics[3];
        $newer = $metrics->take(3);
        $performanceMetrics = $metricType === 'weight_reps'
            ? ['weight','repetitions','best_set','volume','estimated_one_rep_max']
            : ['repetitions','best_set'];
        $improved = collect($performanceMetrics)
            ->contains(fn (string $metric) => $newer->max($metric) > $oldest[$metric]);

        if (! $improved && $oldest['rir'] !== null) {
            $newerRir = $newer->whereNotNull('rir')->max('rir');
            $improved = $newerRir !== null && $newerRir > $oldest['rir'];
        }

        if ($improved) {
            return null;
        }

        return [
            'detected' => true,
            'reason' => 'No se detecta una mejora clara durante cuatro exposiciones comparables.',
            'action' => 'Revisar descanso, recuperacion y tecnica antes de cambiar el plan.',
        ];
    }
}
