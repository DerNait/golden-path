<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoutineDayRequest;
use App\Http\Requests\RoutineExerciseRequest;
use App\Http\Resources\RoutineResource;
use App\Models\Exercise;
use App\Models\Routine;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class RoutineController extends Controller
{
    public function show(Request $request): RoutineResource { return new RoutineResource($this->routine($request)); }
    public function update(Request $request): RoutineResource { $routine=$this->routine($request); $routine->update($request->validate(['name'=>['required','string','max:150'],'description'=>['nullable','string','max:2000']])); return new RoutineResource($this->routine($request)); }
    public function storeDay(RoutineDayRequest $request): JsonResponse
    {
        $routine=$this->routine($request); $data=$request->validated(); $data['position']=$data['weekday'];
        if ($routine->days()->where('weekday',$data['weekday'])->exists()) return response()->json(['message'=>'Ya existe un dia para ese dia de la semana.'],422);
        return response()->json(['data'=>$routine->days()->create($data)],201);
    }
    public function updateDay(RoutineDayRequest $request, RoutineDay $routineDay): JsonResponse { $this->ownDay($request,$routineDay); $routineDay->update($request->validated()); return response()->json(['data'=>$routineDay]); }
    public function destroyDay(Request $request, RoutineDay $routineDay): JsonResponse { $this->ownDay($request,$routineDay); $routineDay->delete(); return response()->json([],204); }
    public function reorderDays(Request $request): JsonResponse
    {
        $ids=$request->validate(['ids'=>['required','array','size:7'],'ids.*'=>['integer','distinct','exists:routine_days,id']])['ids']; $routine=$this->routine($request);
        if ($routine->days()->whereIn('id',$ids)->count()!==7) abort(403);
        DB::transaction(function () use ($ids,$routine): void { foreach ($ids as $index=>$id) $routine->days()->whereKey($id)->update(['position'=>$index+1]); });
        return response()->json(['message'=>'Orden actualizado.']);
    }
    public function storeExercise(RoutineExerciseRequest $request, RoutineDay $routineDay): JsonResponse
    {
        $this->ownDay($request,$routineDay); $data=$request->validated(); $exercise=Exercise::where('user_id',$request->user()->id)->findOrFail($data['exercise_id']);
        $data['exercise_id']=$exercise->id; $data['position']=$routineDay->exercises()->max('position')+1;
        return response()->json(['data'=>$routineDay->exercises()->create($data)->load('exercise')],201);
    }
    public function updateExercise(RoutineExerciseRequest $request, RoutineExercise $routineExercise): JsonResponse { $this->ownExercise($request,$routineExercise); $routineExercise->update($request->safe()->except('exercise_id')); return response()->json(['data'=>$routineExercise->load('exercise')]); }
    public function destroyExercise(Request $request, RoutineExercise $routineExercise): JsonResponse { $this->ownExercise($request,$routineExercise); $routineExercise->delete(); return response()->json([],204); }
    public function reorderExercises(Request $request): JsonResponse
    {
        $data=$request->validate(['routine_day_id'=>['required','integer','exists:routine_days,id'],'ids'=>['required','array'],'ids.*'=>['integer','distinct','exists:routine_exercises,id']]);
        $day=RoutineDay::findOrFail($data['routine_day_id']); $this->ownDay($request,$day);
        if ($day->exercises()->whereIn('id',$data['ids'])->count() !== count($data['ids'])) abort(403);
        if (count($data['ids'])!==$day->exercises()->count()) return response()->json(['message'=>'Envia todos los ejercicios del dia para reordenar.'],422);
        DB::transaction(function () use ($data,$day): void { foreach ($data['ids'] as $index=>$id) $day->exercises()->whereKey($id)->update(['position'=>$index+1]); });
        return response()->json(['message'=>'Orden actualizado.']);
    }
    private function routine(Request $request): Routine { return $request->user()->routines()->where('is_active',true)->with(['days.exercises.exercise.muscleGroups','days.exercises.exercise.alternativeExercises.muscleGroups'])->firstOrFail(); }
    private function ownDay(Request $request, RoutineDay $day): void { if ($day->routine->user_id !== $request->user()->id) abort(403); }
    private function ownExercise(Request $request, RoutineExercise $exercise): void { if ($exercise->routineDay->routine->user_id !== $request->user()->id) abort(403); }
}
