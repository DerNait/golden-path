<?php

namespace App\Http\Controllers\Api\Assistant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Assistant\RoutineSlotRequest;
use App\Models\AssistantNote;
use App\Models\Exercise;
use App\Models\RoutineDay;
use App\Models\RoutineExercise;
use App\Services\Assistant\AssistantAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Routine edits made by the assistant. Each one is audited and leaves a
 * "change" note with before/after so the athlete sees what moved and why.
 */
class RoutineChangeController extends Controller
{
    private const LABELS = [
        'exercise_id' => 'ejercicio', 'priority' => 'prioridad', 'target_sets' => 'series', 'minimum_reps' => 'reps mínimas',
        'maximum_reps' => 'reps máximas', 'target_weight' => 'peso objetivo', 'weight_unit' => 'unidad', 'target_rir_min' => 'RIR mínimo',
        'target_rir_max' => 'RIR máximo', 'rest_seconds' => 'descanso (s)', 'weight_increment' => 'incremento', 'notes' => 'notas',
    ];

    public function update(RoutineSlotRequest $request, RoutineExercise $routineExercise): JsonResponse
    {
        $this->own($request, $routineExercise->routineDay);
        $data = $request->validated();
        $reason = $data['reason'];
        unset($data['reason']);
        if ($data === []) throw ValidationException::withMessages(['reason' => 'Envía al menos un campo para cambiar.']);

        $merged = array_merge($routineExercise->only(['minimum_reps', 'maximum_reps', 'target_rir_min', 'target_rir_max']), $data);
        $this->assertRanges($merged);

        $before = $routineExercise->only(array_keys($data));
        $routineExercise->update($data);
        $routineExercise->load('routineDay', 'exercise');

        $changes = $this->diff($before, $routineExercise->only(array_keys($data)));
        if ($changes === []) return response()->json(['message' => 'Sin cambios: los valores ya eran esos.', 'data' => $routineExercise]);

        $this->note($request, $routineExercise->routineDay, $routineExercise->exercise->name, $changes, $reason, [
            'action' => 'update', 'routine_exercise_id' => $routineExercise->id, 'before' => $before, 'after' => $routineExercise->only(array_keys($data)),
        ]);
        AssistantAudit::record($request, 'routine:write', 'routine_exercise:'.$routineExercise->id.' update');

        return response()->json(['message' => 'Rutina actualizada.', 'data' => $routineExercise]);
    }

    public function store(RoutineSlotRequest $request, RoutineDay $routineDay): JsonResponse
    {
        $this->own($request, $routineDay);
        if ($routineDay->day_type !== 'training') throw ValidationException::withMessages(['day' => 'Ese día es de descanso.']);
        $data = $request->validated();
        $reason = $data['reason'];
        unset($data['reason']);
        $this->assertRanges($data);

        $data += ['priority' => 'essential', 'rest_seconds' => 90, 'progression_type' => 'double_progression'];
        $data['position'] = ($routineDay->exercises()->max('position') ?? 0) + 1;
        $slot = $routineDay->exercises()->create($data)->load('exercise');

        $this->note($request, $routineDay, $slot->exercise->name, ['añadido' => "{$slot->target_sets} series"], $reason, [
            'action' => 'add', 'routine_exercise_id' => $slot->id, 'after' => $slot->only(array_keys($data)),
        ]);
        AssistantAudit::record($request, 'routine:write', 'routine_exercise:'.$slot->id.' add');

        return response()->json(['message' => 'Ejercicio añadido a la rutina.', 'data' => $slot], 201);
    }

    public function destroy(Request $request, RoutineExercise $routineExercise): JsonResponse
    {
        $this->own($request, $routineExercise->routineDay);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']])['reason'];
        $routineExercise->load('routineDay', 'exercise');
        $snapshot = $routineExercise->toArray();
        unset($snapshot['routine_day'], $snapshot['exercise']);

        $name = $routineExercise->exercise->name;
        $day = $routineExercise->routineDay;
        $routineExercise->delete();

        $this->note($request, $day, $name, ['quitado' => $name], $reason, ['action' => 'remove', 'before' => $snapshot]);
        AssistantAudit::record($request, 'routine:write', 'routine_exercise:'.$snapshot['id'].' remove');

        return response()->json(['message' => 'Ejercicio quitado de la rutina. El historial se conserva.']);
    }

    private function own(Request $request, RoutineDay $day): void
    {
        if ($day->routine->user_id !== $request->user()->id) abort(403);
    }

    private function assertRanges(array $values): void
    {
        $errors = [];
        if (isset($values['minimum_reps'], $values['maximum_reps']) && $values['maximum_reps'] < $values['minimum_reps']) $errors['maximum_reps'] = 'Las reps máximas no pueden ser menores que las mínimas.';
        if (isset($values['target_rir_min'], $values['target_rir_max']) && $values['target_rir_max'] < $values['target_rir_min']) $errors['target_rir_max'] = 'El RIR máximo no puede ser menor que el mínimo.';
        if ($errors) throw ValidationException::withMessages($errors);
    }

    /** @return array<string,array{from:mixed,to:mixed}> */
    private function diff(array $before, array $after): array
    {
        $out = [];
        foreach ($after as $field => $to) {
            $from = $before[$field] ?? null;
            if (is_numeric($from) && is_numeric($to) ? (float) $from === (float) $to : (string) $from === (string) $to) continue;
            $out[$field] = ['from' => $this->display($field, $from), 'to' => $this->display($field, $to)];
        }

        return $out;
    }

    private function display(string $field, mixed $value): mixed
    {
        return $field === 'exercise_id' && $value ? (Exercise::find($value)?->name ?? $value) : $value;
    }

    private function note(Request $request, RoutineDay $day, string $exercise, array $changes, string $reason, array $payload): void
    {
        $parts = [];
        foreach ($changes as $field => $change) {
            $label = self::LABELS[$field] ?? $field;
            $parts[] = is_array($change) ? "{$label}: {$change['from']} → {$change['to']}" : "{$label}: {$change}";
        }

        AssistantNote::create([
            'user_id' => $request->user()->id,
            'kind' => 'change',
            'for_date' => now()->toDateString(),
            'body' => "{$day->name} · {$exercise} — ".implode('; ', $parts).". Motivo: {$reason}",
            'payload_json' => $payload + ['day' => $day->name, 'exercise' => $exercise, 'reason' => $reason],
            'token_name' => AssistantAudit::tokenName($request),
        ]);
    }
}
