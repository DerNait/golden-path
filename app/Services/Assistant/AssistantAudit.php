<?php

namespace App\Services\Assistant;

use App\Models\AssistantActivityLog;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/** Every write the assistant makes is recorded with the token that made it. */
class AssistantAudit
{
    public static function tokenName(Request $request): ?string
    {
        $token = $request->user()->currentAccessToken();

        return $token instanceof PersonalAccessToken ? $token->name : null;
    }

    public static function record(Request $request, string $ability, string $subject): void
    {
        $token = $request->user()->currentAccessToken();
        $isPersonalToken = $token instanceof PersonalAccessToken;

        AssistantActivityLog::create([
            'user_id' => $request->user()->id,
            'token_id' => $isPersonalToken ? $token->id : null,
            'token_name' => $isPersonalToken ? $token->name : null,
            'ability' => $ability,
            'method' => $request->method(),
            'path' => $request->path(),
            'subject' => mb_substr($subject, 0, 250),
            'created_at' => now(),
        ]);
    }
}
