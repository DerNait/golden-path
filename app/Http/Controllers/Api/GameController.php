<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\XpEvent;
use App\Services\Gamification\GamificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class GameController extends Controller
{
    public function __construct(private readonly GamificationService $gamification) {}
    public function profile(Request $request): JsonResponse { return response()->json(['data'=>$this->gamification->refresh($request->user()->load('profile'))]); }
    public function achievements(Request $request): JsonResponse { $items=Achievement::all()->map(function ($item) use ($request) { $unlocked=\App\Models\UserAchievement::where('user_id',$request->user()->id)->where('achievement_id',$item->id)->first(); return array_merge($item->toArray(),['unlocked_at'=>$unlocked?->unlocked_at]); }); return response()->json(['data'=>$items]); }
    public function xpEvents(Request $request): JsonResponse { return response()->json(XpEvent::where('user_id',$request->user()->id)->latest()->paginate(30)); }
}
