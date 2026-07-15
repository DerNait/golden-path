<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Relabel logged lifting weights from "kg" to "lb".
 *
 * The gym works in pounds, but the app defaulted the weight unit to "kg", so
 * the numbers were entered as pounds yet stored/displayed as kilograms. This
 * only fixes the LABEL (weight_unit) — the numeric values are already the
 * correct pounds, so nothing is converted. Body-weight columns
 * (*_body_weight_kg, waist_cm) are intentionally left untouched.
 *
 * It also bumps the default progression increment from 2.5 to 5, matching the
 * plate steps used in a pound-based gym.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('exercises')->where('default_weight_unit', 'kg')->update(['default_weight_unit' => 'lb']);
        DB::table('exercises')->where('default_increment', 2.5)->update(['default_increment' => 5]);

        DB::table('routine_exercises')->where('weight_unit', 'kg')->update(['weight_unit' => 'lb']);
        DB::table('routine_exercises')->where('weight_increment', 2.5)->update(['weight_increment' => 5]);

        DB::table('workout_sets')->where('weight_unit', 'kg')->update(['weight_unit' => 'lb']);
        DB::table('personal_records')->where('weight_unit', 'kg')->update(['weight_unit' => 'lb']);
        DB::table('progression_recommendations')->where('weight_unit', 'kg')->update(['weight_unit' => 'lb']);
    }

    public function down(): void
    {
        // Best-effort label reversal; the numeric values are never touched.
        DB::table('exercises')->where('default_weight_unit', 'lb')->update(['default_weight_unit' => 'kg']);
        DB::table('exercises')->where('default_increment', 5)->update(['default_increment' => 2.5]);

        DB::table('routine_exercises')->where('weight_unit', 'lb')->update(['weight_unit' => 'kg']);
        DB::table('routine_exercises')->where('weight_increment', 5)->update(['weight_increment' => 2.5]);

        DB::table('workout_sets')->where('weight_unit', 'lb')->update(['weight_unit' => 'kg']);
        DB::table('personal_records')->where('weight_unit', 'lb')->update(['weight_unit' => 'kg']);
        DB::table('progression_recommendations')->where('weight_unit', 'lb')->update(['weight_unit' => 'kg']);
    }
};
