<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssistantActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
