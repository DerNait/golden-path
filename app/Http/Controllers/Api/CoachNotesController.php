<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssistantNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** What the assistant wrote or changed, for the athlete's own screens. */
class CoachNotesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'limit' => ['nullable', 'integer', 'between:1,50'],
        ]);

        $notes = AssistantNote::where('user_id', $request->user()->id)
            ->when($data['date'] ?? null, fn ($q, $date) => $q->where('for_date', $date))
            ->latest('for_date')->latest('id')->limit($data['limit'] ?? 6)->get();

        return response()->json(['data' => $notes->map->toBrief()->values()]);
    }
}
