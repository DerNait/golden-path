<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend body measurements with core body-composition metrics (e.g. from an
 * Evolt 360 bioscan). All fields are nullable so existing weight/waist-only
 * measurements remain valid. Values are stored in kilograms, the native unit
 * of the scan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->decimal('body_fat_percentage', 5, 2)->nullable()->after('waist_cm');
            $table->decimal('fat_mass_kg', 6, 2)->nullable()->after('body_fat_percentage');
            $table->decimal('lean_mass_kg', 6, 2)->nullable()->after('fat_mass_kg');
            $table->decimal('skeletal_muscle_mass_kg', 6, 2)->nullable()->after('lean_mass_kg');
            $table->decimal('total_body_water_kg', 6, 2)->nullable()->after('skeletal_muscle_mass_kg');
            $table->unsignedTinyInteger('visceral_fat_level')->nullable()->after('total_body_water_kg');
            $table->unsignedSmallInteger('basal_metabolic_rate_kcal')->nullable()->after('visceral_fat_level');
        });
    }

    public function down(): void
    {
        Schema::table('body_measurements', function (Blueprint $table): void {
            $table->dropColumn([
                'body_fat_percentage',
                'fat_mass_kg',
                'lean_mass_kg',
                'skeletal_muscle_mass_kg',
                'total_body_water_kg',
                'visceral_fat_level',
                'basal_metabolic_rate_kcal',
            ]);
        });
    }
};
