<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\AvatarPhase;
use App\Models\GameProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GamificationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $phases = [
                ['Fase 1 - Iniciado', 'iniciado', 0, 0, 0, false, '#60a5fa'],
                ['Fase 2 - Despertado', 'despertado', 600, 6, 0, false, '#4ade80'],
                ['Fase 3 - Forjado', 'forjado', 1800, 20, 1, false, '#fbbf24'],
                ['Fase 4 - Ascendente', 'ascendente', 4000, 40, 0, true, '#a78bfa'],
                ['Fase 5 - Forma Nexo', 'forma-nexo', 8000, 100, 8, false, '#f87171'],
            ];

            foreach ($phases as $index => $phase) {
                $savedPhases[] = AvatarPhase::updateOrCreate(['slug' => $phase[1]], [
                    'name' => $phase[0],
                    'position' => $index + 1,
                    'description' => 'Una expresion visual de disciplina y constancia sostenida.',
                    'minimum_xp' => $phase[2],
                    'minimum_completed_workouts' => $phase[3],
                    'minimum_perfect_weeks' => $phase[4],
                    'requires_completed_phase' => $phase[5],
                    'visual_config_json' => ['color' => $phase[6], 'aura' => $index + 1, 'particles' => $index * 3],
                ]);
            }

            $achievements = [
                ['Primer entrenamiento', 'first-workout', 'workouts_completed', 1, 100, 'fa-dumbbell'],
                ['Primera semana perfecta', 'first-perfect-week', 'perfect_weeks', 1, 150, 'fa-calendar-check'],
                ['Primer record', 'first-record', 'records', 1, 75, 'fa-trophy'],
                ['10 entrenamientos', '10-workouts', 'workouts_completed', 10, 150, 'fa-fire'],
                ['25 entrenamientos', '25-workouts', 'workouts_completed', 25, 250, 'fa-fire'],
                ['50 entrenamientos', '50-workouts', 'workouts_completed', 50, 400, 'fa-fire'],
                ['100 series efectivas', '100-working-sets', 'working_sets', 100, 150, 'fa-list-check'],
                ['500 series efectivas', '500-working-sets', 'working_sets', 500, 400, 'fa-list-check'],
                ['1,000 repeticiones', '1000-repetitions', 'repetitions', 1000, 250, 'fa-arrow-trend-up'],
                ['Primer mes constante', 'consistent-month', 'consistent_months', 1, 300, 'fa-calendar'],
                ['Regreso despues de una pausa', 'return-after-break', 'returns', 1, 100, 'fa-rotate'],
                ['Primera fase completada', 'first-phase', 'phases_completed', 1, 500, 'fa-flag-checkered'],
                ['Mejora en cinco ejercicios', 'five-improvements', 'improved_exercises', 5, 250, 'fa-chart-line'],
                ['Cuatro semanas perfectas', 'four-perfect-weeks', 'perfect_weeks', 4, 500, 'fa-bolt'],
            ];

            foreach ($achievements as $achievement) {
                Achievement::updateOrCreate(['slug' => $achievement[1]], [
                    'name' => $achievement[0],
                    'description' => 'Hito de constancia dentro de Golden Path.',
                    'criteria_type' => $achievement[2],
                    'criteria_value' => $achievement[3],
                    'xp_reward' => $achievement[4],
                    'icon' => $achievement[5],
                ]);
            }

            User::query()->each(function (User $user) use ($savedPhases): void {
                GameProfile::updateOrCreate(['user_id' => $user->id], [
                    'maximum_avatar_phase_id' => $savedPhases[0]->id,
                    'active_avatar_phase_id' => $savedPhases[0]->id,
                ]);
            });
        });
    }
}
