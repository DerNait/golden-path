<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Http\Requests\BodyMeasurementRequest;
use App\Models\BodyMeasurement;
use App\Models\User;
use App\Services\Gamification\GamificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class BodyMeasurementController extends Controller
{
    public function __construct(private readonly GamificationService $gamification) {}
    public function index(Request $request): JsonResponse { return response()->json($request->user()->bodyMeasurements()->latest('recorded_on')->paginate(20)); }
    public function store(BodyMeasurementRequest $request): JsonResponse
    {
        $item=DB::transaction(function () use ($request): BodyMeasurement {
            $item=$request->user()->bodyMeasurements()->create($request->validated()); $this->syncProfile($request->user());
            $week=CarbonImmutable::parse($item->recorded_on)->startOfWeek()->format('Y-m-d'); $this->gamification->rewardMeasurement($request->user(),$week);
            return $item;
        });
        return response()->json(['measurement'=>$item],201);
    }
    public function update(BodyMeasurementRequest $request, BodyMeasurement $bodyMeasurement): JsonResponse { $this->authorize('update',$bodyMeasurement); DB::transaction(function () use ($request,$bodyMeasurement): void { $bodyMeasurement->update($request->validated()); $this->syncProfile($request->user()); }); return response()->json(['measurement'=>$bodyMeasurement]); }
    public function destroy(Request $request, BodyMeasurement $bodyMeasurement): JsonResponse { $this->authorize('delete',$bodyMeasurement); DB::transaction(function () use ($request,$bodyMeasurement): void { $bodyMeasurement->delete(); $this->syncProfile($request->user()); }); return response()->json([],204); }
    private function syncProfile(User $user): void
    {
        $profile=$user->profile;
        $latestWeight=$user->bodyMeasurements()->whereNotNull('body_weight_kg')->latest('recorded_on')->latest('id')->value('body_weight_kg');
        $latestWaist=$user->bodyMeasurements()->whereNotNull('waist_cm')->latest('recorded_on')->latest('id')->value('waist_cm');
        $profile->update([
            'current_body_weight_kg'=>$latestWeight ?? $profile->initial_body_weight_kg,
            'current_waist_cm'=>$latestWaist ?? $profile->initial_waist_cm,
        ]);
    }
}
