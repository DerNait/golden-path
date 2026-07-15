<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestTimerNotification extends Model
{
    protected $guarded = ['id', 'user_id'];

    protected function casts(): array
    {
        return ['ends_at' => 'datetime', 'sent_at' => 'datetime'];
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
