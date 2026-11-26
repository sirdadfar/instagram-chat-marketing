<?php

namespace Tests\Feature;

use App\Jobs\ProcessZernioWebhook;
use App\Models\AppSetting;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FollowGateConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function account(): InstagramAccount
    {
        return InstagramAccount::create([
            'user_id' => User::factory()->create()->id,
            'zernio_account_id' => 'acc_confirmation',
            'username' => 'confirmation_page',
            'status' => 'active',
        ]);
    }

    private function pendingContact(InstagramAccount $account, array $context): Contact
    {
        return Contact::create([
            'instagram_account_id' => $account->id,
            'external_user_id' => 'ig_gate_user',
            'is_follower' => false,
            'metadata' => [
                'follow_gate_pending' => [
                    'token' => 'token_global_1',
                    // The global gate runs before automation matching, so it
                    // stores no automation id.
                    'automation_id' => null,
                    'scope' => 'global',
                    'context' => $context,
                    'created_at' => now()->toIso8601String(),
                    'sent_at' => now()->toIso8601String(),
                ],
            ],
        ]);
    }

    private function confirmationEvent(InstagramAccount $account): WebhookEvent
    {
        return WebhookEvent::create([
            'provider' => 'zernio',
            'event_id' => 'evt_gate_confirmed',
            'event_type' => 'message.received',
            'instagram_account_id' => $account->id,
            'payload' => [
                'id' => 'evt_gate_confirmed',
                'account' => ['accountId' => $account->zernio_account_id],
                'conversation' => ['id' => 'conv_gate_1'],
                'message' => [
                    'id' => 'msg_gate_confirmed',
                    'text' => '',
                    'sender' => [
                        'id' => 'ig_gate_user',
                        'username' => 'gate_user',
                        'instagramProfile' => ['isFollower' => true],
                    ],
                    'metadata' => ['postbackPayload' => 'follow_gate:token_global_1'],
                ],
            ],
            'status' => 'pending',
        ]);
    }

    private function fakeZernio(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);

        Http::fake([
            '*/follow-status/*' => Http::response(['isFollower' => true], 200),
            '*' => Http::response(['success' => true], 200),
        ]);
    }

    public function test_a_confirmed_follow_without_an_automation_sends_the_panel_message(): void
    {
        $this->fakeZernio();
        AppSetting::setValue('follow_gate_confirmed_message', 'CONFIRMED-BUT-NO-RULE');

        $account = $this->account();
        $this->pendingContact($account, [
            'trigger' => 'direct_message',
            'user_id' => 'ig_gate_user',
            'conversation_id' => 'conv_gate_1',
            'text' => 'سلام',
            'instagram_profile' => ['isFollower' => false],
        ]);

        app()->call([new ProcessZernioWebhook($this->confirmationEvent($account)->id), 'handle']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/inbox/conversations/conv_gate_1/messages')
            && (string) data_get($request->data(), 'message') === 'CONFIRMED-BUT-NO-RULE');
    }

    public function test_a_confirmed_follow_replays_the_original_message_through_the_automations(): void
    {
        $this->fakeZernio();
        AppSetting::setValue('automation_global_enabled', true);
        AppSetting::setValue('business_hours_enabled', false);
        AppSetting::setValue('follow_gate_confirmed_message', 'CONFIRMED-BUT-NO-RULE');

        $account = $this->account();
        $automation = Automation::create([
            'instagram_account_id' => $account->id,
            'name' => 'Price DM',
            'trigger' => 'direct_message',
            'status' => 'active',
            'execution_mode' => 'local',
            'global_enabled' => true,
            'match_mode' => 'any',
            'audience' => ['followerStatus' => 'any', 'whenUnknown' => 'send'],
            'settings' => [],
        ]);
        $automation->keywords()->create(['keyword' => 'قیمت', 'match_type' => 'contains']);
        $automation->actions()->create([
            'action' => 'direct_message',
            'sort_order' => 0,
            'config' => ['message' => 'MAIN-PRICE-REPLY'],
        ]);

        $this->pendingContact($account, [
            'trigger' => 'direct_message',
            'user_id' => 'ig_gate_user',
            'conversation_id' => 'conv_gate_1',
            'text' => 'قیمت',
            'instagram_profile' => ['isFollower' => false],
        ]);

        app()->call([new ProcessZernioWebhook($this->confirmationEvent($account)->id), 'handle']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/inbox/conversations/conv_gate_1/messages')
            && (string) data_get($request->data(), 'message') === 'MAIN-PRICE-REPLY');

        // The automation answered: no generic confirmation is needed.
        Http::assertNotSent(fn (Request $request): bool => (string) data_get($request->data(), 'message') === 'CONFIRMED-BUT-NO-RULE');
    }
}
