<?php

namespace Tests\Feature;

use App\Jobs\SendRestTimerNotification;
use App\Models\RestTimerNotification;
use App\Models\User;
use App\Services\Notifications\WebPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        config()->set('services.web_push', [
            'subject' => 'mailto:test@example.com',
            'public_key' => 'public-vapid-key',
            'private_key' => 'private-vapid-key',
        ]);
    }

    public function test_device_subscription_is_saved_and_scoped_to_the_authenticated_user(): void
    {
        $payload = [
            'endpoint' => 'https://push.example.test/subscription/one',
            'keys' => ['p256dh' => str_repeat('p', 64), 'auth' => str_repeat('a', 24)],
            'content_encoding' => 'aes128gcm',
        ];

        $this->postJson('/api/push/subscriptions', $payload)
            ->assertCreated()
            ->assertJsonPath('subscribed', true);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->user->id,
            'endpoint_hash' => hash('sha256', $payload['endpoint']),
            'content_encoding' => 'aes128gcm',
        ]);

        $other = User::factory()->create();
        $this->actingAs($other)
            ->deleteJson('/api/push/subscriptions', ['endpoint' => $payload['endpoint']])
            ->assertNoContent();

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $this->user->id]);
    }

    public function test_rest_notification_is_delayed_and_rescheduling_cancels_the_previous_one(): void
    {
        Queue::fake();

        $first = $this->postJson('/api/rest-timer/notifications', [
            'ends_at' => now()->addMinutes(2)->toIso8601String(),
        ])->assertCreated()->json('token');

        Queue::assertPushed(SendRestTimerNotification::class, fn ($job) => $job->token === $first);
        $this->assertDatabaseHas('rest_timer_notifications', [
            'user_id' => $this->user->id,
            'token' => $first,
            'status' => 'scheduled',
        ]);

        $second = $this->postJson('/api/rest-timer/notifications', [
            'ends_at' => now()->addMinutes(3)->toIso8601String(),
        ])->assertCreated()->json('token');

        $this->assertDatabaseHas('rest_timer_notifications', ['token' => $first, 'status' => 'cancelled']);
        $this->assertDatabaseHas('rest_timer_notifications', ['token' => $second, 'status' => 'scheduled']);

        $this->deleteJson('/api/rest-timer/notifications/current')->assertNoContent();
        $this->assertDatabaseHas('rest_timer_notifications', ['token' => $second, 'status' => 'cancelled']);
    }

    public function test_due_job_sends_once_and_marks_notification_as_sent(): void
    {
        $notification = $this->user->restTimerNotifications()->create([
            'token' => '66ee581b-6f09-4e43-8bc9-dfc1753a9260',
            'ends_at' => now()->subSecond(),
            'status' => 'scheduled',
        ]);

        $webPush = Mockery::mock(WebPushService::class);
        $webPush->shouldReceive('sendRestFinished')
            ->once()
            ->with(Mockery::on(fn (User $user) => $user->is($this->user)))
            ->andReturn(['sent' => 1, 'failed' => 0]);

        (new SendRestTimerNotification($notification->token))->handle($webPush);

        $this->assertSame('sent', $notification->fresh()->status);
        $this->assertNotNull($notification->fresh()->sent_at);
    }

    public function test_cancelled_job_does_not_send(): void
    {
        $notification = $this->user->restTimerNotifications()->create([
            'token' => 'ee89cbea-756a-45fc-9f90-35678bce758d',
            'ends_at' => now()->subSecond(),
            'status' => 'cancelled',
        ]);

        $webPush = Mockery::mock(WebPushService::class);
        $webPush->shouldNotReceive('sendRestFinished');

        (new SendRestTimerNotification($notification->token))->handle($webPush);

        $this->assertSame('cancelled', RestTimerNotification::findOrFail($notification->id)->status);
    }
}
