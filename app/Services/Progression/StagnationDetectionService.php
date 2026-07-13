<?php

namespace App\Services\Progression;

use App\Models\WorkoutExercise;
use Illuminate\Support\Collection;

class StagnationDetectionService
{
    public function detect(Collection $exposures): ?array
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
            ];
        })->values();

        $latest = $metrics[0];
        $older = $metrics->slice(1);
        $improved = collect(['weight','repetitions','best_set','volume','estimated_one_rep_max'])
            ->contains(fn (string $metric) => $latest[$metric] > $older->max($metric));

        if (! $improved && $latest['rir'] !== null) {
            $olderRir = $older->whereNotNull('rir')->max('rir');
            $improved = $olderRir !== null && $latest['rir'] > $olderRir;
        }

        if ($improved) {
            return null;
        }

        return [
            'detected' => true,
            'reason' => 'No se detecta una mejora clara en las ultimas cuatro exposiciones.',
            'action' => 'Mantener una exposicion mas y revisar descanso y recuperacion.',
        ];
    }
}
