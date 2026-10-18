<?php

namespace Tests\Feature;

use App\Jobs\ProcessZernioWebhook;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'top-secret-key';

    private function dispatch(string $payload, ?string $signature): TestResponse
    {
        return $this->call('POST', '/api/webhooks/zernio', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ZERNIO_SIGNATURE' => $signature ?? '',
        ], $payload);
    }

    public function test_returns_503_when_no_secret_is_configured(): void
    {
        config(['zernio.webhook_secret' => null]);

        $this->dispatch('{"id":"evt_1"}', 'whatever')->assertStatus(503);
    }

    public function test_rejects_an_invalid_signature(): void
    {
        config(['zernio.webhook_secret' => self::SECRET]);

        $this->dispatch('{"id":"evt_1"}', 'not-a-valid-signature')->assertStatus(401);
    }

    public function test_accepts_a_signed_payload_and_dispatches_the_job(): void
    {
        Queue::fake();
        config(['zernio.webhook_secret' => self::SECRET]);

        $payload = json_encode(['id' => 'evt_1', 'event' => 'comment.received']);
        $signature = hash_hmac('sha256', $payload, self::SECRET);

        $this->dispatch($payload, $signature)
            ->assertStatus(202)
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'zernio',
            'event_id' => 'evt_1',
            'event_type' => 'comment.received',
        ]);

        Queue::assertPushed(ProcessZernioWebhook::class);
    }

    public function test_duplicate_event_ids_are_deduplicated(): void
    {
        Queue::fake();
        config(['zernio.webhook_secret' => self::SECRET]);

        $payload = json_encode(['id' => 'evt_dup', 'event' => 'comment.received']);
        $signature = hash_hmac('sha256', $payload, self::SECRET);

        $this->dispatch($payload, $signature)->assertStatus(202);
        $this->dispatch($payload, $signature)->assertStatus(200)->assertJson(['duplicate' => true]);

        $this->assertSame(1, WebhookEvent::count());
        Queue::assertPushed(ProcessZernioWebhook::class, 1);
    }

    public function test_invalid_json_is_rejected(): void
    {
        config(['zernio.webhook_secret' => self::SECRET]);

        $payload = '{not-json';
        $this->dispatch($payload, hash_hmac('sha256', $payload, self::SECRET))->assertStatus(400);
    }
}
