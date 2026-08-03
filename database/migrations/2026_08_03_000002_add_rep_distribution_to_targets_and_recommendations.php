<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ideal repetitions per set (for example 10/10/9 for a goal of 29) so the
 * athlete knows how to spread the total instead of guessing. Recommendations
 * may propose one; when none is stored it is derived from the total and the
 * number of sets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progression_recommendations', function (Blueprint $table): void {
            $table->json('suggested_rep_distribution')->nullable()->after('suggested_total_repetitions');
        });

        Schema::table('exercise_targets', function (Blueprint $table): void {
            $table->json('rep_distribution')->nullable()->after('target_total_reps');
        });
    }

    public function down(): void
    {
        Schema::table('progression_recommendations', function (Blueprint $table): void {
            $table->dropColumn('suggested_rep_distribution');
        });

        Schema::table('exercise_targets', function (Blueprint $table): void {
            $table->dropColumn('rep_distribution');
        });
    }
};
