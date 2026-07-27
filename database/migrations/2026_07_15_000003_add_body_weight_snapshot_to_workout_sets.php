<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the athlete's body weight (kg) on each set logged for a
 * bodyweight exercise. Captured at log time so historical volume stays stable,
 * matching the project's snapshot approach. Null for non-bodyweight sets, which
 * keeps their volume unchanged (weight x reps).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workout_sets', function (Blueprint $table): void {
            $table->decimal('body_weight_kg', 6, 2)->nullable()->after('weight_unit');
        });
    }

    public function down(): void
    {
        Schema::table('workout_sets', function (Blueprint $table): void {
            $table->dropColumn('body_weight_kg');
        });
    }
};
