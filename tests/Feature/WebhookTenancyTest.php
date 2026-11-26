<?php

namespace Tests\Feature;

use App\Jobs\ProcessZernioWebhook;
use App\Models\AppSetting;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Services\Automation\WebhookEventAdapter;
use App\Support\Credentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookTenancyTest extends TestCase
{
    use RefreshDatabase;

    private function panelUser(User $user): static
    {
        return $this->actingAs($user)->withSession(['panel_authenticated' => true]);
    }

    private function accountFor(User $owner, string $zernioId, string $username): InstagramAccount
    {
        return InstagramAccount::create([
            'user_id' => $owner->id,
            'zernio_account_id' => $zernioId,
            'username' => $username,
            'status' => 'active',
        ]);
    }

    private function eventFor(?InstagramAccount $account, string $eventId, string $type = 'message.received'): WebhookEvent
    {
        return WebhookEvent::create([
            'provider' => 'zernio',
            'event_id' => $eventId,
            'event_type' => $type,
            'instagram_account_id' => $account?->id,
            'payload' => ['id' => $eventId, 'event' => $type],
            'status' => 'processed',
        ]);
    }

    private function signedPayload(string $secret, array $payload): array
    {
        $raw = json_encode($payload);

        return [$raw, hash_hmac('sha256', $raw, $secret)];
    }

    public function test_a_tenant_only_sees_webhook_events_of_their_own_pages(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ownAccount = $this->accountFor($owner, 'acc_own', 'page_own');
        $otherAccount = $this->accountFor($other, 'acc_other', 'page_other');

        $this->eventFor($ownAccount, 'evt_own');
        $this->eventFor($otherAccount, 'evt_other');
        $this->eventFor(null, 'evt_unattributed');

        $response = $this->panelUser($owner)->get(route('logs'));

        $response->assertOk();
        $response->assertSee('page_own');
        $response->assertDontSee('page_other');
        $response->assertDontSee('evt_other');
    }

    public function test_the_owner_webhook_secret_is_accepted_and_the_event_is_attributed(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $account = $this->accountFor($owner, 'acc_own', 'page_own');
        Credentials::rememberUserWebhookSecret($owner, 'owner-secret');
        config(['zernio.webhook_secret' => 'global-secret']);

        [$raw, $signature] = $this->signedPayload('owner-secret', [
            'id' => 'evt_owner',
            'event' => 'message.received',
            'account' => ['accountId' => 'acc_own'],
            'message' => ['direction' => 'incoming', 'conversationId' => 'conv_1', 'sender' => ['id' => 'u1']],
            'conversation' => ['id' => 'conv_1'],
        ]);

        $this->call('POST', '/api/webhooks/zernio', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ZERNIO_SIGNATURE' => $signature,
        ], $raw)->assertStatus(202);

        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evt_owner',
            'instagram_account_id' => $account->id,
        ]);
    }

    public function test_the_global_secret_still_authenticates_a_tenant_delivery(): void
    {
        Queue::fake();

        $owner = User::factory()->create();
        $this->accountFor($owner, 'acc_own', 'page_own');
        Credentials::rememberUserWebhookSecret($owner, 'owner-secret');
        config(['zernio.webhook_secret' => 'global-secret']);

        [$raw, $signature] = $this->signedPayload('global-secret', [
            'id' => 'evt_global',
            'event' => 'comment.received',
            'account' => ['accountId' => 'acc_own'],
            'comment' => ['id' => 'c1', 'text' => 'hi', 'author' => ['id' => 'u1']],
        ]);

        $this->call('POST', '/api/webhooks/zernio', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ZERNIO_SIGNATURE' => $signature,
        ], $raw)->assertStatus(202);
    }

    public function test_the_general_follow_gate_prompts_every_direct_message(): void
    {
        $owner = User::factory()->create();
        Credentials::rememberUserKey($owner, 'tenant-key-123');
        $account = $this->accountFor($owner, 'acc_own', 'page_own');
        AppSetting::setValue('follow_gate_enabled', true);

        Http::fake([
            '*' => Http::response(['data' => ['messageId' => 'm1']], 200),
        ]);

        $event = WebhookEvent::create([
            'provider' => 'zernio',
            'event_id' => 'evt_dm',
            'event_type' => 'message.received',
            'instagram_account_id' => $account->id,
            'payload' => [
                'id' => 'evt_dm',
                'event' => 'message.received',
                'account' => ['accountId' => 'acc_own'],
                'conversation' => ['id' => 'conv_1'],
                'message' => [
                    'id' => 'm1',
                    'direction' => 'incoming',
                    'conversationId' => 'conv_1',
                    'text' => 'قیمت',
                    'sender' => [
                        'id' => 'u1',
                        'username' => 'buyer',
                        'instagramProfile' => ['isFollower' => false],
                    ],
                ],
            ],
            'status' => 'pending',
        ]);

        app()->call([new ProcessZernioWebhook($event->id), 'handle']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/inbox/conversations/conv_1/messages')
            && str_contains(json_encode($request->data()), 'follow_gate:'));
        $this->assertSame('processed', $event->fresh()->status);
    }

    public function test_the_adapter_skips_outgoing_echoes_and_standby_messages(): void
    {
        $adapter = new WebhookEventAdapter();

        $base = [
            'id' => 'evt_1',
            'account' => ['accountId' => 'acc_1'],
            'conversation' => ['id' => 'conv_1'],
            'message' => [
                'id' => 'm1',
                'direction' => 'incoming',
                'conversationId' => 'conv_1',
                'text' => 'hello',
                'sender' => ['id' => 'u1'],
            ],
        ];

        $this->assertNotNull($adapter->adapt($base, 'message.received'));

        $outgoing = $base;
        $outgoing['message']['direction'] = 'outgoing';
        $this->assertNull($adapter->adapt($outgoing, 'message.received'));

        $standby = $base;
        $standby['message']['metadata'] = ['standby' => true];
        $this->assertNull($adapter->adapt($standby, 'message.received'));
    }

    public function test_the_adapter_reads_story_replies_from_metadata(): void
    {
        $adapter = new WebhookEventAdapter();

        $data = $adapter->adapt([
            'id' => 'evt_story',
            'account' => ['accountId' => 'acc_1'],
            'conversation' => ['id' => 'conv_1'],
            'message' => [
                'id' => 'm1',
                'direction' => 'incoming',
                'conversationId' => 'conv_1',
                'text' => 'nice story',
                'sender' => ['id' => 'u1'],
                'metadata' => ['storyReply' => ['storyId' => 'story_1', 'storyUrl' => 'https://example.test/story.jpg']],
            ],
        ], 'message.received');

        $this->assertNotNull($data);
        $this->assertSame('story_reply', $data['trigger']);
        $this->assertSame('story_1', $data['story_id']);
    }
}
