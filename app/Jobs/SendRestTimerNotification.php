<?php

namespace App\Jobs;

use App\Models\RestTimerNotification;
use App\Services\Notifications\WebPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendRestTimerNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly string $token) {}

    public function handle(WebPushService $webPush): void
    {
        $notification = RestTimerNotification::query()
            ->with('user.pushSubscriptions')
            ->where('token', $this->token)
            ->where('status', 'scheduled')
            ->first();

        if (! $notification) {
            return;
        }

        if ($notification->ends_at->isFuture()) {
            $this->release(max(1, now()->diffInSeconds($notification->ends_at, false)));

            return;
        }

        $claimed = RestTimerNotification::query()
            ->whereKey($notification->id)
            ->where('status', 'scheduled')
            ->update(['status' => 'sending']);

        if ($claimed !== 1) {
            return;
        }

        $webPush->sendRestFinished($notification->user);
        $notification->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        RestTimerNotification::query()
            ->where('token', $this->token)
            ->whereIn('status', ['scheduled', 'sending'])
            ->update(['status' => 'failed']);
    }
}
