<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Jobs\ProcessZernioWebhook;
use App\Services\Automation\ActionExecutor;
use App\Services\Automation\AutomationEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiTriggerAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function panelUser(User $user): static
    {
        return $this->actingAs($user)->withSession(['panel_authenticated' => true]);
    }

    private function account(User $owner): InstagramAccount
    {
        return InstagramAccount::create([
            'user_id' => $owner->id,
            'zernio_account_id' => 'zernio_multi_1',
            'username' => 'multi_page',
            'status' => 'active',
        ]);
    }

    private function automation(InstagramAccount $account, array $attributes = []): Automation
    {
        return Automation::create(array_merge([
            'instagram_account_id' => $account->id,
            'name' => 'Multi trigger rule',
            'trigger' => 'comment',
            'triggers' => ['comment', 'story_reply', 'direct_message'],
            'status' => 'active',
            'execution_mode' => 'local',
            'global_enabled' => true,
            'match_mode' => 'any',
            'audience' => ['followerStatus' => 'any', 'whenUnknown' => 'send'],
            'settings' => [],
        ], $attributes));
    }

    public function test_the_engine_accepts_every_selected_trigger(): void
    {
        AppSetting::setValue('automation_global_enabled', true);
        AppSetting::setValue('business_hours_enabled', false);

        $account = $this->account(User::factory()->create());
        $automation = $this->automation($account);
        $automation->keywords()->create(['keyword' => 'لیست', 'match_type' => 'contains']);
        $automation->load('keywords');

        $engine = app(AutomationEngine::class);

        foreach (['comment', 'story_reply', 'direct_message'] as $trigger) {
            $result = $engine->evaluate($automation, 'لیست', [
                'trigger' => $trigger,
                'instagram_profile' => ['isFollower' => true],
            ]);

            $this->assertTrue($result['pass'], "trigger {$trigger} should match");
            $this->assertTrue($result['trigger_match']);
        }
    }

    public function test_the_engine_rejects_a_trigger_that_was_not_selected(): void
    {
        AppSetting::setValue('automation_global_enabled', true);
        AppSetting::setValue('business_hours_enabled', false);

        $account = $this->account(User::factory()->create());
        $automation = $this->automation($account, [
            'trigger' => 'comment',
            'triggers' => ['comment'],
        ]);
        $automation->keywords()->create(['keyword' => 'لیست', 'match_type' => 'contains']);
        $automation->load('keywords');

        $result = app(AutomationEngine::class)->evaluate($automation, 'لیست', [
            'trigger' => 'direct_message',
            'instagram_profile' => ['isFollower' => true],
        ]);

        $this->assertFalse($result['pass']);
        $this->assertFalse($result['trigger_match']);
    }

    public function test_legacy_automations_fall_back_to_the_single_trigger_column(): void
    {
        $account = $this->account(User::factory()->create());
        $automation = $this->automation($account, [
            'trigger' => 'story_reply',
            'triggers' => null,
        ]);

        $this->assertSame(['story_reply'], $automation->triggerValues());
        $this->assertFalse($automation->hasMultipleTriggers());
    }

    public function test_the_panel_stores_multiple_triggers_with_trigger_scoped_actions(): void
    {
        $owner = User::factory()->create();
        $account = $this->account($owner);

        $this->panelUser($owner)
            ->post(route('automations.store'), [
                'instagram_account_id' => $account->id,
                'name' => 'لیست و دایرکت',
                'status' => 'active',
                'execution_mode' => 'local',
                'priority' => 100,
                'match_mode' => 'any',
                'keywords' => 'لیست، قیمت',
                'triggers' => ['comment', 'direct_message'],
                'triggers_submitted' => 1,
                'dm_message' => 'متن پاسخ',
                'audience_follower_status' => 'any',
                'audience_unknown' => 'send',
                'cooldown_seconds' => 30,
                'follow_gate_enabled' => 0,
            ])
            ->assertRedirect();

        $automation = Automation::latest('id')->firstOrFail();

        $this->assertSame('comment', $automation->trigger->value);
        $this->assertSame(['comment', 'direct_message'], $automation->triggers);
        $this->assertSame('local', $automation->execution_mode);

        $private = $automation->actions()->where('action', 'private_reply')->firstOrFail();
        $direct = $automation->actions()->where('action', 'direct_message')->firstOrFail();

        $this->assertSame(['comment'], $private->config['triggers']);
        $this->assertSame(['direct_message'], $direct->config['triggers']);
        $this->assertSame('متن پاسخ', $direct->config['message']);
    }

    public function test_switching_to_multiple_triggers_pauses_the_native_copy(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $owner = User::factory()->create();
        $account = $this->account($owner);
        $automation = $this->automation($account, [
            'trigger' => 'comment',
            'triggers' => ['comment'],
            'execution_mode' => 'zernio',
            'zernio_automation_id' => 'remote_comment_1',
        ]);

        $this->panelUser($owner)
            ->put(route('automations.update', $automation), [
                'name' => $automation->name,
                'status' => 'active',
                'execution_mode' => 'zernio',
                'priority' => 100,
                'cooldown_seconds' => 30,
                'match_mode' => 'any',
                'keywords' => 'لیست',
                'triggers' => ['comment', 'story_reply'],
                'triggers_submitted' => 1,
                'dm_message' => 'متن پاسخ',
                'audience_follower_status' => 'any',
                'audience_unknown' => 'send',
                'follow_gate_enabled' => 0,
            ])
            ->assertRedirect();

        $automation->refresh();

        $this->assertSame(['comment', 'story_reply'], $automation->triggers);
        $this->assertSame('local', $automation->execution_mode);
        $this->assertNull($automation->zernio_automation_id);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_contains($request->url(), '/comment-automations/remote_comment_1')
            && $request->data()['isActive'] === false);
    }

    public function test_the_panel_renders_multi_trigger_automations(): void
    {
        $owner = User::factory()->create();
        $account = $this->account($owner);
        $automation = $this->automation($account);
        $automation->actions()->create([
            'action' => 'direct_message',
            'sort_order' => 0,
            'config' => ['message' => 'سلام', 'triggers' => ['comment', 'story_reply', 'direct_message']],
        ]);

        $this->panelUser($owner)->get(route('automations.index'))
            ->assertOk()
            ->assertSeeText('نظر + پاسخ استوری + پیام مستقیم');

        $this->panelUser($owner)->get(route('automations.edit', $automation))
            ->assertOk()
            ->assertSeeText('محرک‌ها');

        $this->panelUser($owner)->get(route('dashboard'))->assertOk();
    }

    public function test_the_follow_gate_callback_answers_the_original_story_trigger(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);

        Http::fake([
            '*/follow-status/*' => Http::response(['isFollower' => true], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        AppSetting::setValue('follow_gate_enabled', true);

        $account = $this->account(User::factory()->create());
        $automation = $this->automation($account, [
            'triggers' => ['comment', 'story_reply'],
            'audience' => ['followerStatus' => 'follower', 'whenUnknown' => 'verify'],
            'settings' => ['followGateEnabled' => true],
        ]);
        $automation->actions()->create([
            'action' => 'private_reply',
            'sort_order' => 0,
            'config' => ['message' => 'MAIN-COMMENT', 'triggers' => ['comment']],
        ]);
        $automation->actions()->create([
            'action' => 'direct_message',
            'sort_order' => 1,
            'config' => ['message' => 'MAIN-STORY', 'triggers' => ['story_reply']],
        ]);

        Contact::create([
            'instagram_account_id' => $account->id,
            'external_user_id' => 'ig_story_user',
            'is_follower' => false,
            'metadata' => [
                'follow_gate_pending' => [
                    'token' => 'token_story_1',
                    'automation_id' => $automation->id,
                    'context' => [
                        'trigger' => 'story_reply',
                        'user_id' => 'ig_story_user',
                        'story_id' => 'story_1',
                        'event_id' => 'evt_original',
                    ],
                    'created_at' => now()->toIso8601String(),
                ],
            ],
        ]);

        $event = WebhookEvent::create([
            'provider' => 'zernio',
            'event_id' => 'evt_gate_story',
            'event_type' => 'message.received',
            'payload' => [
                'id' => 'evt_gate_story',
                'account' => ['accountId' => $account->zernio_account_id, 'username' => 'multi_page'],
                'conversation' => ['id' => 'conv_story_1'],
                'message' => [
                    'id' => 'msg_story_1',
                    'text' => '',
                    'sender' => [
                        'id' => 'ig_story_user',
                        'username' => 'story_user',
                        'instagramProfile' => ['isFollower' => true],
                    ],
                    'metadata' => ['postbackPayload' => 'follow_gate:token_story_1'],
                ],
            ],
        ]);

        app()->call([new ProcessZernioWebhook($event->id), 'handle']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/inbox/conversations/conv_story_1/messages')
            && (string) data_get($request->data(), 'message') === 'MAIN-STORY');

        Http::assertNotSent(fn (Request $request): bool => (string) data_get($request->data(), 'message') === 'MAIN-COMMENT');
    }

    public function test_a_comment_is_gated_before_the_main_private_reply(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);

        Http::fake([
            '*/follow-status/*' => Http::response(['isFollower' => false], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', false);

        $account = $this->account(User::factory()->create());
        $automation = $this->automation($account, [
            'triggers' => ['comment'],
            'audience' => ['followerStatus' => 'follower', 'whenUnknown' => 'verify'],
            'settings' => ['followGateEnabled' => true],
        ]);
        $automation->load('account');

        $result = app(ActionExecutor::class)->execute($automation, [
            'action' => 'private_reply',
            'config' => ['message' => 'MAIN PRIVATE REPLY'],
        ], [
            'trigger' => 'comment',
            'user_id' => 'ig_commenter_1',
            'post_id' => 'post_1',
            'comment_id' => 'comment_1',
            'instagram_profile' => ['isFollower' => false],
        ]);

        $this->assertSame('waiting_follow', $result['status']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/private-reply')
            && (string) data_get($request->data(), 'message') !== 'MAIN PRIVATE REPLY'
            && (string) data_get($request->data(), 'buttons.0.payload') !== '');

        Http::assertNotSent(fn (Request $request): bool => (string) data_get($request->data(), 'message') === 'MAIN PRIVATE REPLY');
    }

    public function test_a_follower_receives_the_main_reply_without_a_gate_prompt(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);

        Http::fake([
            '*/follow-status/*' => Http::response(['isFollower' => true], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        $account = $this->account(User::factory()->create());
        $automation = $this->automation($account, [
            'triggers' => ['comment'],
            'audience' => ['followerStatus' => 'follower', 'whenUnknown' => 'verify'],
            'settings' => ['followGateEnabled' => true],
        ]);
        $automation->load('account');

        $result = app(ActionExecutor::class)->execute($automation, [
            'action' => 'private_reply',
            'config' => ['message' => 'MAIN PRIVATE REPLY'],
        ], [
            'trigger' => 'comment',
            'user_id' => 'ig_follower',
            'post_id' => 'post_1',
            'comment_id' => 'comment_2',
            'instagram_profile' => ['isFollower' => true],
        ]);

        $this->assertSame('sent', $result['status']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/private-reply')
            && (string) data_get($request->data(), 'message') === 'MAIN PRIVATE REPLY');
    }
}
