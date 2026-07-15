<?php

namespace App\Services\Notifications;

use App\Models\PushSubscription as StoredSubscription;
use App\Models\User;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use RuntimeException;

class WebPushService
{
    /** @return array{sent: int, failed: int} */
    public function sendRestFinished(User $user): array
    {
        $configuration = config('services.web_push');

        if (empty($configuration['subject']) || empty($configuration['public_key']) || empty($configuration['private_key'])) {
            throw new RuntimeException('Web Push is not configured.');
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $configuration['subject'],
                'publicKey' => $configuration['public_key'],
                'privateKey' => $configuration['private_key'],
            ],
        ], [
            'TTL' => 300,
            'urgency' => 'high',
            'topic' => 'rest-timer',
        ], 10);

        $payload = json_encode([
            'title' => 'Descanso terminado',
            'body' => 'Es momento de comenzar tu siguiente serie.',
            'url' => '/workout',
            'tag' => 'golden-path-rest-timer',
        ], JSON_THROW_ON_ERROR);

        $subscriptions = $user->pushSubscriptions()->get()->keyBy('endpoint');

        foreach ($subscriptions as $stored) {
            $webPush->queueNotification(new Subscription(
                $stored->endpoint,
                $stored->public_key,
                $stored->auth_token,
                $stored->content_encoding,
            ), $payload);
        }

        $sent = 0;
        $failed = 0;

        foreach ($webPush->flush() as $report) {
            /** @var StoredSubscription|null $stored */
            $stored = $subscriptions->get($report->getEndpoint());

            if ($report->isSuccess()) {
                $sent++;
            } else {
                $failed++;
            }

            if ($stored && $report->isSubscriptionExpired()) {
                $stored->delete();
            }
        }

        return compact('sent', 'failed');
    }
}
