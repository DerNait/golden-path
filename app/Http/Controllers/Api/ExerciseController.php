<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\ExerciseRequest;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Models\ExerciseAlternative;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
class ExerciseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query=$request->user()->exercises()->with(['muscleGroups','alternativeExercises.muscleGroups']);
        $query->when($request->filled('search'),fn ($q)=>$q->where('name','like','%'.$request->string('search').'%'))
            ->when($request->filled('muscle_group'),fn ($q)=>$q->whereHas('muscleGroups',fn ($g)=>$g->where('slug',$request->string('muscle_group'))))
            ->when($request->filled('equipment'),fn ($q)=>$q->where('equipment',$request->string('equipment')))
            ->when(! $request->boolean('include_inactive'),fn ($q)=>$q->where('is_active',true));
        return ExerciseResource::collection($query->orderBy('name')->paginate(min(100,max(1,$request->integer('per_page',30)))));
    }
    public function store(ExerciseRequest $request): ExerciseResource
    {
        $exercise=DB::transaction(function () use ($request): Exercise {
            $data=$request->safe()->except('muscle_group_ids'); $data['slug']=$this->uniqueSlug($request->user()->id,$data['name']);
            $exercise=$request->user()->exercises()->create($data);
            $exercise->muscleGroups()->sync($request->validated('muscle_group_ids',[]));
            return $exercise;
        });
        return new ExerciseResource($exercise->load(['muscleGroups','alternativeExercises']));
    }
    public function show(Request $request, Exercise $exercise): ExerciseResource { $this->authorize('view',$exercise); return new ExerciseResource($exercise->load(['muscleGroups','alternativeExercises.muscleGroups'])); }
    public function update(ExerciseRequest $request, Exercise $exercise): ExerciseResource
    {
        $this->authorize('update',$exercise);
        DB::transaction(function () use ($request,$exercise): void {
            $data=$request->safe()->except('muscle_group_ids');
            if ($exercise->name!==$data['name']) $data['slug']=$this->uniqueSlug($request->user()->id,$data['name'],$exercise->id);
            $exercise->update($data);
            $exercise->muscleGroups()->sync($request->validated('muscle_group_ids',[]));
        });
        return new ExerciseResource($exercise->load(['muscleGroups','alternativeExercises.muscleGroups']));
    }
    public function destroy(Request $request, Exercise $exercise): JsonResponse { $this->authorize('delete',$exercise); $exercise->update(['is_active'=>false]); return response()->json(['message'=>'Ejercicio desactivado.']); }
    public function uploadImage(Request $request, Exercise $exercise): ExerciseResource
    {
        $this->authorize('update',$exercise); $request->validate(['image'=>['required','image','mimes:jpg,jpeg,png,webp','max:4096']]);
        $oldPath=$exercise->image_path;
        $path=$request->file('image')->store('exercises','public');
        try {
            $exercise->update(['image_path'=>$path]);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }
        if ($oldPath) Storage::disk('public')->delete($oldPath);
        return new ExerciseResource($exercise->fresh('muscleGroups'));
    }
    public function deleteImage(Request $request, Exercise $exercise): JsonResponse { $this->authorize('update',$exercise); $path=$exercise->image_path; $exercise->update(['image_path'=>null]); if ($path) Storage::disk('public')->delete($path); return response()->json(['message'=>'Imagen eliminada.']); }
    public function addAlternative(Request $request, Exercise $exercise): JsonResponse
    {
        $this->authorize('update',$exercise); $data=$request->validate(['alternative_exercise_id'=>['required','integer','different:exercise','exists:exercises,id'],'notes'=>['nullable','string','max:500']]);
        if ((int) $data['alternative_exercise_id'] === $exercise->id) return response()->json(['message'=>'Un ejercicio no puede ser su propia alternativa.'],422);
        $alternative=$request->user()->exercises()->findOrFail($data['alternative_exercise_id']);
        $relation=ExerciseAlternative::firstOrCreate(['exercise_id'=>$exercise->id,'alternative_exercise_id'=>$alternative->id],['position'=>$exercise->alternatives()->max('position')+1,'notes'=>$data['notes']??null]);
        return response()->json(['data'=>$relation->load('alternativeExercise')],201);
    }
    public function deleteAlternative(Request $request, ExerciseAlternative $exerciseAlternative): JsonResponse { $this->authorize('update',$exerciseAlternative->exercise); $exerciseAlternative->delete(); return response()->json([],204); }
    public function reorderAlternatives(Request $request): JsonResponse
    {
        $data=$request->validate(['exercise_id'=>['required','integer','exists:exercises,id'],'ids'=>['required','array'],'ids.*'=>['integer','distinct','exists:exercise_alternatives,id']]); $exercise=Exercise::findOrFail($data['exercise_id']); $this->authorize('update',$exercise);
        DB::transaction(function () use ($data,$exercise): void { foreach ($data['ids'] as $index=>$id) $exercise->alternatives()->whereKey($id)->update(['position'=>$index+1]); }); return response()->json(['message'=>'Orden actualizado.']);
    }
    private function uniqueSlug(int $userId,string $name,?int $ignore=null): string { $base=Str::slug($name); $slug=$base; $index=2; while (Exercise::where('user_id',$userId)->where('slug',$slug)->when($ignore,fn ($q)=>$q->whereKeyNot($ignore))->exists()) $slug=$base.'-'.$index++; return $slug; }
}
