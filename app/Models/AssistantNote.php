<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantNote extends Model
{
    public const KINDS = ['note', 'summary', 'change'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['for_date' => 'date:Y-m-d', 'payload_json' => 'array'];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Shape shared by the athlete app and the assistant API. */
    public function toBrief(): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'for_date' => $this->for_date->toDateString(),
            'body' => $this->body,
            'workout_session_id' => $this->workout_session_id,
            'details' => $this->payload_json,
            'author' => $this->token_name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
