<?php

namespace App\Services\Workouts;

use App\Models\Routine;
use App\Models\RoutineDay;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "What is next" is the next training day in the routine's order, not the day
 * of the week: a missed Thursday must not make Upper B disappear. After a long
 * break the cycle restarts at its first day.
 */
class RotationService
{
    /** Days without a session after which the cycle starts over. */
    public const RESTART_AFTER_DAYS = 14;

    public function next(User $user, ?Routine $routine = null): ?RoutineDay
    {
        $routine ??= $user->routines()->where('is_active', true)->first();
        if (! $routine) return null;

        $days = $this->trainingDays($routine);
        if ($days->isEmpty()) return null;

        $active = $user->workouts()->where('status', 'in_progress')->first();
        if ($active && ($day = $days->firstWhere('id', $active->routine_day_id))) return $day;

        $last = $user->workouts()->whereIn('status', ['completed', 'partial'])
            ->whereIn('routine_day_id', $days->pluck('id'))->latest('started_at')->first();
        if (! $last || $last->started_at->lt(now()->subDays(self::RESTART_AFTER_DAYS))) return $days->first();

        $index = $days->search(fn (RoutineDay $day) => $day->id === $last->routine_day_id);

        return $days[($index + 1) % $days->count()];
    }

    /** @return Collection<int,RoutineDay> */
    private function trainingDays(Routine $routine): Collection
    {
        return $routine->days()->where('day_type', 'training')->orderBy('position')->orderBy('weekday')->get()->values();
    }
}
