<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendRestTimerNotification;
use App\Models\PushSubscription;
use App\Models\RestTimerNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PushNotificationController extends Controller
{
    public function config(): JsonResponse
    {
        $configuration = config('services.web_push');

        return response()->json([
            'enabled' => filled($configuration['subject'])
                && filled($configuration['public_key'])
                && filled($configuration['private_key']),
            'public_key' => $configuration['public_key'],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:4096'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:512'],
            'content_encoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        $subscription = PushSubscription::query()
            ->firstOrNew(['endpoint_hash' => hash('sha256', $data['endpoint'])]);

        // user_id is guarded, so assign every attribute through forceFill. All
        // values are server-derived or validated, and this keeps the global
        // endpoint lookup while reassigning the subscription to the current user.
        $subscription->forceFill([
            'user_id' => $request->user()->id,
            'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'],
            'auth_token' => $data['keys']['auth'],
            'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
        ])->save();

        return response()->json(['subscribed' => true, 'id' => $subscription->id], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:4096']]);

        $request->user()->pushSubscriptions()
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json([], 204);
    }

    public function scheduleRestTimer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ends_at' => ['required', 'date', 'after:now', 'before_or_equal:+20 minutes'],
        ]);
        $endsAt = CarbonImmutable::parse($data['ends_at']);

        $notification = DB::transaction(function () use ($request, $endsAt): RestTimerNotification {
            $request->user()->restTimerNotifications()
                ->where('status', 'scheduled')
                ->update(['status' => 'cancelled']);

            return $request->user()->restTimerNotifications()->create([
                'token' => (string) Str::uuid(),
                'ends_at' => $endsAt,
                'status' => 'scheduled',
            ]);
        });

        SendRestTimerNotification::dispatch($notification->token)
            ->delay($endsAt)
            ->afterCommit();

        return response()->json([
            'token' => $notification->token,
            'ends_at' => $notification->ends_at->toIso8601String(),
        ], 201);
    }

    public function cancelRestTimer(Request $request): JsonResponse
    {
        $request->user()->restTimerNotifications()
            ->where('status', 'scheduled')
            ->update(['status' => 'cancelled']);

        return response()->json([], 204);
    }
}
