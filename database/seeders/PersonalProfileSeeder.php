<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PersonalProfileSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = User::firstOrNew([
                'email' => env('PERSONAL_USER_EMAIL', 'owner@example.com'),
            ]);

            $user->name = env('PERSONAL_USER_NAME', 'DerNait');

            // The initial secret is only for account creation. Re-running a
            // seed must never undo a password changed later from Mi perfil.
            if (! $user->exists) {
                $user->password = env('PERSONAL_USER_PASSWORD', 'password');
            }

            $user->save();

            $user->profile()->updateOrCreate([], [
                'age' => 22,
                'height_cm' => 169.5,
                'biological_sex' => null,
                'initial_body_weight_kg' => 58.97,
                'current_body_weight_kg' => 58.97,
                'initial_waist_cm' => null,
                'current_waist_cm' => null,
                'preferred_body_weight_unit' => 'lb',
                'objective' => 'body_recomposition',
                'experience_level' => 'returning_beginner',
                'weekly_workout_goal' => 4,
                'target_session_minutes' => 75,
                'maximum_session_minutes' => 90,
                'protein_goal_min_grams' => 100,
                'protein_goal_max_grams' => 130,
                'sleep_goal_hours' => 7,
                'notes' => 'Peso normal con poca masa muscular y acumulacion abdominal descrita. Cintura pendiente de registrar.',
            ]);
        });
    }
}
