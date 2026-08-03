<?php

namespace App\Http\Controllers\Api\Assistant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\RecommendationDraftRequest;
use App\Models\AssistantActivityLog;
use App\Services\Assistant\RecommendationDraftService;
use Illuminate\Http\JsonResponse;

class RecommendationDraftController extends Controller
{
    public function __construct(private readonly RecommendationDraftService $drafts) {}

    public function store(RecommendationDraftRequest $request): JsonResponse
    {
        $created = $this->drafts->create($request->user(), $request->validated()['drafts']);

        $token = $request->user()->currentAccessToken();
        $isPersonalToken = $token instanceof \Laravel\Sanctum\PersonalAccessToken;
        AssistantActivityLog::create([
            'user_id' => $request->user()->id,
            'token_id' => $isPersonalToken ? $token->id : null,
            'token_name' => $isPersonalToken ? $token->name : null,
            'ability' => 'recommendations:write',
            'method' => $request->method(),
            'path' => $request->path(),
            'subject' => 'recommendation_drafts:'.$created->pluck('id')->implode(','),
            'created_at' => now(),
        ]);

        return response()->json([
            'created' => $created->map(fn ($r) => [
                'id' => $r->id, 'exercise_id' => $r->exercise_id, 'status' => $r->status,
                'type' => $r->recommendation_type, 'confidence' => $r->confidence,
                'suggested_weight' => $r->suggested_weight !== null ? (float) $r->suggested_weight : null,
                'suggested_total_repetitions' => $r->suggested_total_repetitions,
                'weight_unit' => $r->weight_unit, 'source' => 'assistant',
            ])->values(),
        ], 201);
    }
}
