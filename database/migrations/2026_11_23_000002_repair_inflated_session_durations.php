<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sessions left open overnight were closed days later and stored the whole gap
 * as their duration (e.g. 2898 minutes). Recompute those from the last logged
 * set, which is when the workout really ended.
 */
return new class extends Migration
{
    private const SUSPICIOUS_SECONDS = 4 * 3600;
    private const TAIL_SECONDS = 300;

    public function up(): void
    {
        $sessions = DB::table('workout_sessions')
            ->whereIn('status', ['completed', 'partial'])
            ->where('duration_seconds', '>', self::SUSPICIOUS_SECONDS)
            ->get(['id', 'started_at']);

        foreach ($sessions as $session) {
            $lastSet = DB::table('workout_sets')
                ->join('workout_exercises', 'workout_exercises.id', '=', 'workout_sets.workout_exercise_id')
                ->where('workout_exercises.workout_session_id', $session->id)
                ->whereNotNull('workout_sets.completed_at')
                ->max('workout_sets.completed_at');
            if (! $lastSet) continue;

            $seconds = strtotime($lastSet) - strtotime($session->started_at) + self::TAIL_SECONDS;
            if ($seconds > 0 && $seconds < self::SUSPICIOUS_SECONDS) {
                DB::table('workout_sessions')->where('id', $session->id)->update(['duration_seconds' => $seconds]);
            }
        }
    }

    public function down(): void
    {
        // The previous values were wrong; nothing to restore.
    }
};
