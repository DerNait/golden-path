<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\GameProfile;
use App\Models\ProgressionRecommendation;
use App\Models\RoutineExercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->user=User::where('email','owner@example.com')->firstOrFail();
        $this->actingAs($this->user);
    }

    public function test_profile_can_be_read_updated_and_validated(): void
    {
        $this->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('user.email','owner@example.com')
            ->assertJsonPath('profile.age',22)
            ->assertJsonPath('profile.current_body_weight_kg','58.97');

        $payload=[
            'age'=>23,
            'height_cm'=>170,
            'preferred_body_weight_unit'=>'kg',
            'objective'=>'strength',
            'weekly_workout_goal'=>5,
            'target_session_minutes'=>60,
            'maximum_session_minutes'=>80,
            'protein_goal_min_grams'=>110,
            'protein_goal_max_grams'=>140,
            'sleep_goal_hours'=>8,
            'notes'=>'Perfil actualizado desde la prueba.',
        ];

        $this->putJson('/api/profile',$payload)
            ->assertOk()
            ->assertJsonPath('profile.age',23)
            ->assertJsonPath('profile.objective','strength')
            ->assertJsonPath('profile.weekly_workout_goal',5);

        $this->putJson('/api/profile',array_merge($payload,[
            'age'=>12,
            'maximum_session_minutes'=>30,
            'protein_goal_max_grams'=>90,
        ]))->assertUnprocessable()->assertJsonValidationErrors([
            'age','maximum_session_minutes','protein_goal_max_grams',
        ]);
    }

    public function test_measurements_crud_keeps_latest_profile_values_in_sync(): void
    {
        $older=$this->postJson('/api/body-measurements',[
            'recorded_on'=>today()->subDays(2)->format('Y-m-d'),
            'body_weight_kg'=>60.5,
            'notes'=>'Base de prueba',
        ])->assertCreated()->json('measurement');

        $waist=$this->postJson('/api/body-measurements',[
            'recorded_on'=>today()->subDay()->format('Y-m-d'),
            'waist_cm'=>72.4,
        ])->assertCreated()->json('measurement');

        $newer=$this->postJson('/api/body-measurements',[
            'recorded_on'=>today()->format('Y-m-d'),
            'body_weight_kg'=>61.2,
        ])->assertCreated()->json('measurement');

        $this->assertDatabaseHas('user_profiles',[
            'user_id'=>$this->user->id,
            'current_body_weight_kg'=>61.2,
            'current_waist_cm'=>72.4,
        ]);

        $this->putJson("/api/body-measurements/{$older['id']}",[
            'recorded_on'=>today()->subDays(2)->format('Y-m-d'),
            'body_weight_kg'=>59.8,
        ])->assertOk();
        $this->assertSame('61.20',$this->user->profile()->value('current_body_weight_kg'));

        $this->deleteJson("/api/body-measurements/{$newer['id']}")->assertNoContent();
        $this->assertSame('59.80',$this->user->profile()->value('current_body_weight_kg'));

        $this->deleteJson("/api/body-measurements/{$waist['id']}")->assertNoContent();
        $this->assertNull($this->user->profile()->value('current_waist_cm'));

        $this->deleteJson("/api/body-measurements/{$older['id']}")->assertNoContent();
        $this->assertSame('58.97',$this->user->profile()->value('current_body_weight_kg'));

        $this->postJson('/api/body-measurements',[
            'recorded_on'=>today()->addDay()->format('Y-m-d'),
            'body_weight_kg'=>62,
        ])->assertUnprocessable()->assertJsonValidationErrors('recorded_on');

        $this->postJson('/api/body-measurements',[
            'recorded_on'=>today()->format('Y-m-d'),
        ])->assertUnprocessable()->assertJsonValidationErrors('measurement');
    }

    public function test_exercise_image_can_be_uploaded_replaced_and_deleted(): void
    {
        Storage::fake('public');
        $exercise=$this->user->exercises()->firstOrFail();

        $first=$this->postJson("/api/exercises/{$exercise->id}/image",[
            'image'=>UploadedFile::fake()->image('first.jpg',800,600),
        ])->assertOk()->json('data.image_path');
        Storage::disk('public')->assertExists($first);

        $second=$this->postJson("/api/exercises/{$exercise->id}/image",[
            'image'=>UploadedFile::fake()->image('second.png',640,480),
        ])->assertOk()->json('data.image_path');
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->deleteJson("/api/exercises/{$exercise->id}/image")->assertOk();
        Storage::disk('public')->assertMissing($second);
        $this->assertNull($exercise->fresh()->image_path);

        $this->postJson("/api/exercises/{$exercise->id}/image",[
            'image'=>UploadedFile::fake()->create('invalid.pdf',10,'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_recommendations_can_be_accepted_ignored_and_modified_once(): void
    {
        $planned=RoutineExercise::with('exercise')->firstOrFail();

        $accepted=$this->recommendation($planned,[
            'suggested_weight'=>22.5,
            'suggested_total_repetitions'=>null,
        ]);
        $this->postJson("/api/progression/recommendations/{$accepted->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status','accepted');
        $this->assertSame('22.50',$planned->fresh()->target_weight);
        $this->postJson("/api/progression/recommendations/{$accepted->id}/accept")->assertUnprocessable();

        $ignored=$this->recommendation($planned,['suggested_weight'=>30]);
        $this->postJson("/api/progression/recommendations/{$ignored->id}/ignore")
            ->assertOk()
            ->assertJsonPath('data.status','ignored');
        $this->assertSame('22.50',$planned->fresh()->target_weight);
        $this->postJson("/api/progression/recommendations/{$ignored->id}/modify",[
            'suggested_weight'=>25,
        ])->assertUnprocessable();

        $modified=$this->recommendation($planned,['suggested_weight'=>24]);
        $this->postJson("/api/progression/recommendations/{$modified->id}/modify",[
            'suggested_weight'=>25,
            'suggested_total_repetitions'=>27,
        ])->assertOk()->assertJsonPath('data.status','modified');
        $planned->refresh();
        $this->assertSame('25.00',$planned->target_weight);
        $this->assertSame(27,$planned->progression_target_total_reps);
        $this->assertSame(10,$planned->progression_target_reps);
        $this->assertNotNull($modified->fresh()->accepted_at);
    }

    public function test_history_filters_by_partial_training_name_and_exposes_summary_fields(): void
    {
        $this->user->workouts()->create([
            'name'=>'Upper A',
            'started_at'=>now()->subDay(),
            'finished_at'=>now()->subDay()->addHour(),
            'status'=>'completed',
            'duration_seconds'=>3600,
            'total_volume'=>1500,
            'working_sets_count'=>12,
            'xp_earned'=>125,
        ]);
        $this->user->workouts()->create([
            'name'=>'Lower B',
            'started_at'=>now(),
            'status'=>'in_progress',
        ]);

        $this->getJson('/api/workouts?name=Upper&status=completed')
            ->assertOk()
            ->assertJsonCount(1,'data')
            ->assertJsonPath('data.0.name','Upper A')
            ->assertJsonPath('data.0.xp_earned',125)
            ->assertJsonPath('data.0.records_count',0);
    }

    public function test_youtube_reference_is_normalized_exposed_in_routine_and_optional(): void
    {
        $planned=RoutineExercise::with('exercise.muscleGroups')->firstOrFail();
        $exercise=$planned->exercise;
        $videoId='dQw4w9WgXcQ';
        $canonical="https://www.youtube.com/watch?v={$videoId}";
        $embed="https://www.youtube-nocookie.com/embed/{$videoId}";

        $this->putJson("/api/exercises/{$exercise->id}",$this->exercisePayload($exercise,[
            'youtube_url'=>"https://youtu.be/{$videoId}?si=reference",
        ]))
            ->assertOk()
            ->assertJsonPath('data.youtube_url',$canonical)
            ->assertJsonPath('data.youtube_embed_url',$embed);

        $this->assertDatabaseHas('exercises',['id'=>$exercise->id,'youtube_url'=>$canonical]);

        $routine=$this->getJson('/api/routine')->assertOk()->json('data');
        $routineItem=collect($routine['days'])
            ->flatMap(fn (array $day): array => $day['exercises'])
            ->first(fn (array $item): bool => $item['exercise']['id'] === $exercise->id);
        $this->assertSame($canonical,$routineItem['exercise']['youtube_url']);
        $this->assertSame($embed,$routineItem['exercise']['youtube_embed_url']);

        $created=$this->postJson('/api/exercises',$this->exercisePayload($exercise,[
            'name'=>'Ejercicio con video',
            'youtube_url'=>"https://www.youtube.com/shorts/{$videoId}",
        ]))->assertSuccessful();
        $created->assertJsonPath('data.youtube_url',$canonical)->assertJsonPath('data.youtube_embed_url',$embed);

        foreach ([
            'http://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
            'https://www.youtube.com/watch?v=short',
        ] as $invalidUrl) {
            $this->putJson("/api/exercises/{$exercise->id}",$this->exercisePayload($exercise,[
                'youtube_url'=>$invalidUrl,
            ]))->assertUnprocessable()->assertJsonValidationErrors('youtube_url');
        }

        $this->putJson("/api/exercises/{$exercise->id}",$this->exercisePayload($exercise,[
            'youtube_url'=>null,
        ]))->assertOk()->assertJsonPath('data.youtube_url',null)->assertJsonPath('data.youtube_embed_url',null);
        $this->assertDatabaseHas('exercises',['id'=>$exercise->id,'youtube_url'=>null]);
    }

    public function test_game_profile_is_recreated_if_it_is_missing(): void
    {
        GameProfile::where('user_id',$this->user->id)->delete();

        $this->getJson('/api/game/profile')
            ->assertOk()
            ->assertJsonPath('data.user_id',$this->user->id)
            ->assertJsonPath('data.level',1);

        $this->assertDatabaseHas('game_profiles',['user_id'=>$this->user->id]);
    }


    public function test_routine_reordering_requires_complete_owned_lists(): void
    {
        $routine=$this->user->routines()->where('is_active',true)->with('days.exercises')->firstOrFail();
        $dayIds=$routine->days->pluck('id')->all();
        $reversedDays=array_reverse($dayIds);

        $this->postJson('/api/routine-days/reorder',['ids'=>$reversedDays])->assertOk();
        foreach($reversedDays as $position=>$id) {
            $this->assertDatabaseHas('routine_days',['id'=>$id,'position'=>$position+1]);
        }

        $this->postJson('/api/routine-days/reorder',['ids'=>array_slice($dayIds,0,6)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        $trainingDay=$routine->days->firstWhere('day_type','training');
        $exerciseIds=$trainingDay->exercises->pluck('id')->all();
        $reversedExercises=array_reverse($exerciseIds);

        $this->postJson('/api/routine-exercises/reorder',[
            'routine_day_id'=>$trainingDay->id,
            'ids'=>$reversedExercises,
        ])->assertOk();
        foreach($reversedExercises as $position=>$id) {
            $this->assertDatabaseHas('routine_exercises',['id'=>$id,'position'=>$position+1]);
        }

        $this->postJson('/api/routine-exercises/reorder',[
            'routine_day_id'=>$trainingDay->id,
            'ids'=>array_slice($exerciseIds,0,-1),
        ])->assertUnprocessable();

        $foreignUser=User::factory()->create();
        $foreignRoutine=$foreignUser->routines()->create(['name'=>'Rutina ajena','is_active'=>true]);
        $foreignDay=$foreignRoutine->days()->create([
            'name'=>'Dia ajeno',
            'weekday'=>1,
            'day_type'=>'training',
            'position'=>1,
        ]);
        $mixedDays=$dayIds;
        $mixedDays[0]=$foreignDay->id;

        $this->postJson('/api/routine-days/reorder',['ids'=>$mixedDays])->assertForbidden();
    }

    private function exercisePayload(Exercise $exercise,array $overrides=[]): array
    {
        $exercise->loadMissing('muscleGroups');

        return array_merge([
            'name'=>$exercise->name,
            'description'=>$exercise->description,
            'instructions'=>$exercise->instructions,
            'equipment'=>$exercise->equipment,
            'youtube_url'=>$exercise->youtube_url,
            'metric_type'=>$exercise->metric_type,
            'weight_mode'=>$exercise->weight_mode,
            'default_weight_unit'=>$exercise->default_weight_unit,
            'default_increment'=>$exercise->default_increment,
            'is_active'=>$exercise->is_active,
            'muscle_group_ids'=>$exercise->muscleGroups->pluck('id')->all(),
        ],$overrides);
    }

    private function recommendation(RoutineExercise $planned,array $overrides=[]): ProgressionRecommendation
    {
        return ProgressionRecommendation::create(array_merge([
            'user_id'=>$this->user->id,
            'exercise_id'=>$planned->exercise_id,
            'routine_exercise_id'=>$planned->id,
            'recommendation_type'=>'increase_weight',
            'current_weight'=>20,
            'suggested_weight'=>22,
            'weight_unit'=>'kg',
            'reason'=>'Decision explicable de prueba.',
            'confidence'=>'high',
            'status'=>'pending',
        ],$overrides));
    }
}
