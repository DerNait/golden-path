<?php

namespace App\Http\Controllers\Api\Assistant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\NoteRequest;
use App\Models\AssistantNote;
use App\Services\Assistant\AssistantAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Notes the assistant leaves for the athlete (and reads back later). */
class NoteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since' => ['nullable', 'date_format:Y-m-d'],
            'kind' => ['nullable', 'in:note,summary,change'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $notes = AssistantNote::where('user_id', $request->user()->id)
            ->when($data['since'] ?? null, fn ($q, $since) => $q->where('for_date', '>=', $since))
            ->when($data['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind))
            ->latest('for_date')->latest('id')->limit($data['limit'] ?? 30)->get();

        return response()->json(['data' => $notes->map->toBrief()->values()]);
    }

    public function store(NoteRequest $request): JsonResponse
    {
        $data = $request->validated();
        $note = AssistantNote::create([
            'user_id' => $request->user()->id,
            'workout_session_id' => $data['workout_session_id'] ?? null,
            'kind' => $data['kind'] ?? 'note',
            'for_date' => $data['for_date'] ?? now()->toDateString(),
            'body' => $data['body'],
            'token_name' => AssistantAudit::tokenName($request),
        ]);
        AssistantAudit::record($request, 'notes:write', 'assistant_note:'.$note->id);

        return response()->json(['message' => 'Nota guardada: la ves en Inicio y en el historial.', 'data' => $note->toBrief()], 201);
    }
}
