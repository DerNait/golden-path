<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Recommendations now come only from the AI assistant and apply as soon as
 * they are published. The same exercise can be planned at different set
 * counts (three sets one day, two another, or as an alternative), so each
 * recommendation records the set count it was written for.
 *
 * The automatic engine no longer runs; any of its recommendations still
 * waiting for a decision are retired so they cannot be applied later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('progression_recommendations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('target_sets')->nullable()->after('routine_exercise_id');
        });

        DB::table('progression_recommendations')
            ->where('status', 'pending')
            ->where(fn ($query) => $query->whereNull('metadata_json->source')->orWhere('metadata_json->source', '!=', 'assistant'))
            ->update(['status' => 'superseded', 'updated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('progression_recommendations', function (Blueprint $table): void {
            $table->dropColumn('target_sets');
        });
    }
};
