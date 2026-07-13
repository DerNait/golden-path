<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_profiles', function (Blueprint $table): void {
            $table->decimal('adherence_last_28_days',5,2)->default(0)->after('perfect_weeks');
        });
    }

    public function down(): void
    {
        Schema::table('game_profiles', function (Blueprint $table): void {
            $table->dropColumn('adherence_last_28_days');
        });
    }
};
