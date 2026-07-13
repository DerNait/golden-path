<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\ExerciseAlternative;
use App\Models\MuscleGroup;
use App\Models\Routine;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Models\TrainingPhase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrainingPlanSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $user = User::where('email', env('PERSONAL_USER_EMAIL', 'owner@example.com'))->firstOrFail();
            $groups = $this->seedMuscleGroups();
            $exercises = $this->seedExercises($user, $groups);
            $routine = Routine::updateOrCreate(
                ['user_id' => $user->id, 'is_active' => true],
                ['name' => 'Upper / Lower - Recomposicion inicial', 'description' => 'Cuatro dias, doble progresion y RIR objetivo 1-2. Sesiones de 60 a 85 minutos.'],
            );
            $days = $this->seedDays($routine);
            $this->seedRoutineExercises($days, $exercises);
            $this->seedAlternatives($exercises);
            $this->seedTrainingPhase($user, $routine);
        });
    }

    private function seedMuscleGroups(): Collection
    {
        return collect([
            'Pecho', 'Espalda', 'Hombros', 'Biceps', 'Triceps', 'Cuadriceps', 'Isquiotibiales',
            'Gluteos', 'Pantorrillas', 'Abdominales', 'Antebrazos', 'Cuerpo completo', 'Otro',
        ])->mapWithKeys(function (string $name): array {
            $group = MuscleGroup::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
            return [$name => $group];
        });
    }

    private function seedExercises(User $user, Collection $groups): Collection
    {
        $catalog = [
            'Press inclinado con mancuernas'=>['mancuernas','per_dumbbell','Pecho'],
            'Press inclinado en maquina'=>['maquina','machine_stack','Pecho'],
            'Press inclinado con barra'=>['barra','total','Pecho'],
            'Remo con pecho apoyado'=>['maquina','machine_stack','Espalda'],
            'Remo en maquina'=>['maquina','machine_stack','Espalda'],
            'Remo sentado en polea'=>['polea','machine_stack','Espalda'],
            'Press militar sentado'=>['mancuernas','per_dumbbell','Hombros'],
            'Press de hombros en maquina'=>['maquina','machine_stack','Hombros'],
            'Jalon al pecho'=>['polea','machine_stack','Espalda'],
            'Dominadas asistidas'=>['maquina asistida','machine_stack','Espalda'],
            'Elevaciones laterales'=>['mancuernas','per_dumbbell','Hombros'],
            'Elevaciones laterales en cable'=>['polea','machine_stack','Hombros'],
            'Elevaciones laterales en maquina'=>['maquina','machine_stack','Hombros'],
            'Extension de triceps en polea'=>['polea','machine_stack','Triceps'],
            'Fondos asistidos para triceps'=>['maquina asistida','machine_stack','Triceps'],
            'Curl inclinado con mancuernas'=>['mancuernas','per_dumbbell','Biceps'],
            'Curl con cable'=>['polea','machine_stack','Biceps'],
            'Curl con barra EZ'=>['barra EZ','total','Biceps'],
            'Sentadilla hack'=>['maquina','machine_stack','Cuadriceps'],
            'Prensa de piernas'=>['maquina','machine_stack','Cuadriceps'],
            'Sentadilla goblet pesada'=>['mancuerna','total','Cuadriceps'],
            'Peso muerto rumano'=>['barra','total','Isquiotibiales'],
            'Bisagra de cadera en Smith'=>['Smith','total','Isquiotibiales'],
            'Curl femoral sentado'=>['maquina','machine_stack','Isquiotibiales'],
            'Curl femoral acostado'=>['maquina','machine_stack','Isquiotibiales'],
            'Extension de cuadriceps'=>['maquina','machine_stack','Cuadriceps'],
            'Split squat con paso corto'=>['mancuernas','per_dumbbell','Cuadriceps'],
            'Elevacion de pantorrillas'=>['maquina','machine_stack','Pantorrillas'],
            'Pantorrilla en prensa'=>['maquina','machine_stack','Pantorrillas'],
            'Crunch en polea'=>['polea','machine_stack','Abdominales'],
            'Crunch en maquina'=>['maquina','machine_stack','Abdominales'],
            'Plancha'=>['peso corporal','not_applicable','Abdominales','duration'],
            'Press de pecho en maquina'=>['maquina','machine_stack','Pecho'],
            'Press de banca plano'=>['barra','total','Pecho'],
            'Flexiones con progresion adecuada'=>['peso corporal','bodyweight','Pecho','bodyweight_reps'],
            'Jalon al pecho con agarre neutro'=>['polea','machine_stack','Espalda'],
            'Dominadas asistidas con agarre neutro'=>['maquina asistida','machine_stack','Espalda'],
            'Remo unilateral con mancuerna'=>['mancuerna','per_dumbbell','Espalda'],
            'Press plano con mancuernas'=>['mancuernas','per_dumbbell','Pecho'],
            'Reverse pec deck'=>['maquina','machine_stack','Hombros'],
            'Face pull'=>['polea','machine_stack','Hombros'],
            'Extension de triceps sobre la cabeza en cable'=>['polea','machine_stack','Triceps'],
            'Extension de triceps con cuerda'=>['polea','machine_stack','Triceps'],
            'Sentadilla frontal ligera'=>['barra','total','Cuadriceps'],
            'Sentadilla bulgara'=>['mancuernas','per_dumbbell','Cuadriceps'],
            'Zancadas caminando'=>['mancuernas','per_dumbbell','Cuadriceps'],
            'Hip thrust'=>['barra','total','Gluteos'],
            'Puente de gluteos en maquina'=>['maquina','machine_stack','Gluteos'],
            'Hip thrust en Smith'=>['Smith','total','Gluteos'],
            'Curl femoral'=>['maquina','machine_stack','Isquiotibiales'],
            'Peso muerto rumano ligero'=>['barra','total','Isquiotibiales'],
            'Pantorrilla en maquina'=>['maquina','machine_stack','Pantorrillas'],
            'Elevacion de rodillas'=>['peso corporal','bodyweight','Abdominales','bodyweight_reps'],
        ];

        return collect($catalog)->mapWithKeys(function (array $config, string $name) use ($user, $groups): array {
            $hasWeight = $config[1] !== 'not_applicable';
            $exercise = Exercise::updateOrCreate(['user_id' => $user->id, 'slug' => Str::slug($name)], [
                'name' => $name,
                'equipment' => $config[0],
                'weight_mode' => $config[1],
                'metric_type' => $config[3] ?? 'weight_reps',
                'default_weight_unit' => $hasWeight ? 'kg' : null,
                'default_increment' => $hasWeight ? 2.5 : null,
                'is_active' => true,
                'instructions' => 'Prioriza una tecnica controlada. Detente ante una molestia inusual.',
            ]);
            $exercise->muscleGroups()->sync([$groups[$config[2]]->id => ['is_primary' => true]]);
            return [$name => $exercise];
        });
    }

    private function seedDays(Routine $routine): Collection
    {
        $schedule = [
            1=>['Upper A','training',75], 2=>['Lower A','training',75], 3=>['Descanso miercoles','rest',null],
            4=>['Upper B','training',80], 5=>['Lower B','training',75], 6=>['Descanso sabado','rest',null],
            7=>['Descanso domingo','rest',null],
        ];

        return collect($schedule)->mapWithKeys(function (array $config, int $weekday) use ($routine): array {
            $day = RoutineDay::updateOrCreate(['routine_id'=>$routine->id,'weekday'=>$weekday], [
                'name'=>$config[0],'day_type'=>$config[1],'position'=>$weekday,'estimated_minutes'=>$config[2],
            ]);
            return [$config[0] => $day];
        });
    }

    private function seedRoutineExercises(Collection $days, Collection $exercises): void
    {
        $plan = [
            'Upper A'=>[
                ['Press inclinado con mancuernas',3,6,10,10,150,'essential'],
                ['Remo con pecho apoyado',3,6,10,10,150,'essential'],
                ['Press militar sentado',2,6,10,10,120,'essential'],
                ['Jalon al pecho',2,8,12,12,120,'essential'],
                ['Elevaciones laterales',2,12,20,18,75,'recommended'],
                ['Extension de triceps en polea',2,10,15,15,75,'optional'],
                ['Curl inclinado con mancuernas',2,10,15,15,75,'optional'],
            ],
            'Lower A'=>[
                ['Sentadilla hack',3,6,10,10,180,'essential'],
                ['Peso muerto rumano',3,6,10,10,150,'essential'],
                ['Curl femoral sentado',2,10,15,15,90,'essential'],
                ['Extension de cuadriceps',2,10,15,15,90,'essential'],
                ['Elevacion de pantorrillas',2,10,15,15,75,'recommended'],
                ['Crunch en polea',3,10,15,15,60,'optional'],
            ],
            'Upper B'=>[
                ['Press de pecho en maquina',3,6,10,10,150,'essential'],
                ['Jalon al pecho con agarre neutro',3,6,10,10,150,'essential'],
                ['Remo sentado en polea',2,8,12,12,120,'essential'],
                ['Press inclinado en maquina',2,8,12,12,120,'essential'],
                ['Reverse pec deck',2,12,20,18,75,'recommended'],
                ['Elevaciones laterales',2,12,20,18,75,'recommended'],
                ['Curl con cable',2,10,15,15,60,'optional','UPPER_B_ARMS'],
                ['Extension de triceps sobre la cabeza en cable',2,10,15,15,60,'optional','UPPER_B_ARMS'],
            ],
            'Lower B'=>[
                ['Prensa de piernas',3,8,12,12,150,'essential'],
                ['Sentadilla bulgara',2,8,12,12,120,'essential'],
                ['Hip thrust',2,8,12,12,120,'essential'],
                ['Curl femoral',2,10,15,15,90,'essential'],
                ['Pantorrilla en maquina',2,10,15,15,75,'recommended'],
                ['Elevacion de rodillas',3,10,15,15,60,'optional'],
            ],
        ];

        foreach ($plan as $dayName => $items) {
            foreach ($items as $index => $item) {
                $exercise = $exercises[$item[0]];
                RoutineExercise::updateOrCreate(
                    ['routine_day_id'=>$days[$dayName]->id,'exercise_id'=>$exercise->id],
                    [
                        'position'=>$index+1,'priority'=>$item[6],'target_sets'=>$item[1],
                        'minimum_reps'=>$item[2],'maximum_reps'=>$item[3],'progression_target_reps'=>$item[4],
                        'target_weight'=>null,'weight_unit'=>$exercise->default_weight_unit,'target_rir_min'=>1,'target_rir_max'=>2,
                        'rest_seconds'=>$item[5],'weight_increment'=>$exercise->default_increment,
                        'progression_type'=>'double_progression','superset_group'=>$item[7] ?? null,
                    ],
                );
            }
        }
    }

    private function seedAlternatives(Collection $exercises): void
    {
        $alternatives = [
            'Press inclinado con mancuernas'=>['Press inclinado en maquina','Press inclinado con barra'],
            'Remo con pecho apoyado'=>['Remo en maquina','Remo sentado en polea'],
            'Press militar sentado'=>['Press de hombros en maquina'],
            'Jalon al pecho'=>['Dominadas asistidas'],
            'Elevaciones laterales'=>['Elevaciones laterales en cable','Elevaciones laterales en maquina'],
            'Extension de triceps en polea'=>['Fondos asistidos para triceps'],
            'Curl inclinado con mancuernas'=>['Curl con cable','Curl con barra EZ'],
            'Sentadilla hack'=>['Prensa de piernas','Sentadilla goblet pesada'],
            'Peso muerto rumano'=>['Bisagra de cadera en Smith'],
            'Curl femoral sentado'=>['Curl femoral acostado'],
            'Extension de cuadriceps'=>['Split squat con paso corto'],
            'Elevacion de pantorrillas'=>['Pantorrilla en prensa'],
            'Crunch en polea'=>['Crunch en maquina','Plancha'],
            'Press de pecho en maquina'=>['Press de banca plano','Flexiones con progresion adecuada'],
            'Jalon al pecho con agarre neutro'=>['Dominadas asistidas con agarre neutro'],
            'Remo sentado en polea'=>['Remo unilateral con mancuerna'],
            'Press inclinado en maquina'=>['Press inclinado con mancuernas','Press plano con mancuernas'],
            'Reverse pec deck'=>['Face pull'],
            'Curl con cable'=>['Curl con barra EZ'],
            'Extension de triceps sobre la cabeza en cable'=>['Extension de triceps con cuerda'],
            'Prensa de piernas'=>['Sentadilla frontal ligera','Sentadilla hack'],
            'Sentadilla bulgara'=>['Zancadas caminando'],
            'Hip thrust'=>['Puente de gluteos en maquina','Hip thrust en Smith'],
            'Curl femoral'=>['Peso muerto rumano ligero'],
            'Pantorrilla en maquina'=>['Pantorrilla en prensa'],
            'Elevacion de rodillas'=>['Crunch en polea','Crunch en maquina','Plancha'],
        ];

        foreach ($alternatives as $main => $items) {
            foreach ($items as $index => $alternative) {
                ExerciseAlternative::updateOrCreate([
                    'exercise_id'=>$exercises[$main]->id,
                    'alternative_exercise_id'=>$exercises[$alternative]->id,
                ], ['position'=>$index+1]);
            }
        }
    }

    private function seedTrainingPhase(User $user, Routine $routine): void
    {
        $start = CarbonImmutable::now()->startOfWeek();
        TrainingPhase::updateOrCreate(['user_id'=>$user->id,'status'=>'active'], [
            'routine_id'=>$routine->id,
            'name'=>'Fase 1 - Recomposicion inicial',
            'description'=>'Metas orientativas de recomposicion. No son diagnosticos ni garantias.',
            'starts_on'=>$start,
            'ends_on'=>$start->addWeeks(12)->subDay(),
            'planned_sessions'=>48,
            'minimum_target_sessions'=>40,
            'target_weight_min_kg'=>58,
            'target_weight_max_kg'=>60,
            'target_waist_reduction_min_cm'=>2,
            'target_waist_reduction_max_cm'=>5,
            'protein_goal_min_grams'=>100,
            'protein_goal_max_grams'=>130,
            'sleep_goal_hours'=>7,
        ]);
    }
}
