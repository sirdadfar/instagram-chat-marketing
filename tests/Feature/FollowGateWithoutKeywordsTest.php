<?php

namespace Tests\Feature;

use App\Enums\AutomationTrigger;
use App\Models\AppSetting;
use App\Models\Automation;
use App\Models\Contact;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Services\Automation\ActionExecutor;
use App\Services\Automation\AutomationEngine;
use App\Support\FollowGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

class FollowGateWithoutKeywordsTest extends TestCase
{
    use RefreshDatabase;

    private function automation(): Automation
    {
        $user = User::factory()->create();
        $account = InstagramAccount::create([
            'user_id' => $user->id,
            'zernio_account_id' => 'acc_gate',
            'username' => 'gate_page',
            'status' => 'active',
        ]);

        $automation = Automation::create([
            'instagram_account_id' => $account->id,
            'name' => 'DM gate',
            'trigger' => AutomationTrigger::DirectMessage,
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
            'config' => ['message' => 'سلام'],
        ]);

        return $automation->load(['keywords', 'actions', 'account']);
    }

    private function evaluate(Automation $automation, string $text, array $overrides = []): array
    {
        AppSetting::setValue('automation_global_enabled', true);
        AppSetting::setValue('business_hours_enabled', false);

        return app(AutomationEngine::class)->evaluate($automation, $text, array_merge([
            'trigger' => 'direct_message',
            'user_id' => 'ig_user_1',
            'conversation_id' => 'conv_1',
            'instagram_profile' => ['isFollower' => false],
        ], $overrides));
    }

    public function test_a_keywordless_message_gets_the_follow_gate_when_enabled(): void
    {
        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);

        $result = $this->evaluate($this->automation(), 'سلام');

        $this->assertTrue($result['pass']);
        $this->assertTrue($result['gate_only']);
        $this->assertFalse($result['keywords']['pass']);
    }

    public function test_keywordless_gate_is_off_by_default(): void
    {
        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', false);

        $result = $this->evaluate($this->automation(), 'سلام');

        $this->assertFalse($result['pass']);
        $this->assertFalse($result['gate_only']);
    }

    public function test_an_excluded_keyword_never_triggers_the_gate(): void
    {
        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);

        $automation = $this->automation();
        $automation->keywords()->create(['keyword' => 'توقف', 'match_type' => 'contains', 'is_excluded' => true]);
        $automation->load('keywords');

        $result = $this->evaluate($automation, 'توقف');

        $this->assertFalse($result['pass']);
        $this->assertFalse($result['gate_only']);
    }

    public function test_a_matching_keyword_is_not_marked_as_gate_only(): void
    {
        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);

        $result = $this->evaluate($this->automation(), 'قیمت');

        $this->assertTrue($result['pass']);
        $this->assertFalse($result['gate_only']);
    }

    public function test_enforce_follow_gate_prompts_a_non_follower_once(): void
    {
        // HTTP is faked, but the client still requires a non-empty key before issuing a request.
        config(['zernio.api_key' => 'test-zernio-key']);

        Http::fake([
            '*/follow-status/*' => Http::response(['isFollower' => false], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);

        $automation = $this->automation();
        $context = [
            'trigger' => 'direct_message',
            'user_id' => 'ig_user_1',
            'conversation_id' => 'conv_1',
            'instagram_profile' => ['isFollower' => false],
        ];

        $executor = app(ActionExecutor::class);

        $this->assertTrue($executor->enforceFollowGate($automation, $context));

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/messages')
            && str_contains((string) ($request->data()['message'] ?? ''), 'فالو'));

        $contact = Contact::where('instagram_account_id', $automation->instagram_account_id)
            ->where('external_user_id', 'ig_user_1')
            ->first();

        $this->assertNotNull(data_get($contact?->metadata, 'follow_gate_pending.token'));

        // A second message within the day must not nag the same contact again.
        $this->assertFalse($executor->enforceFollowGate($automation, $context));
    }

    public function test_enforce_follow_gate_stays_silent_for_followers(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);

        Http::fake([
            '*/follow-status/*' => Http::response(['isFollower' => true], 200),
        ]);

        $this->assertFalse(app(ActionExecutor::class)->enforceFollowGate($this->automation(), [
            'trigger' => 'direct_message',
            'user_id' => 'ig_user_2',
            'conversation_id' => 'conv_2',
            'instagram_profile' => ['isFollower' => true],
        ]));
    }

    public function test_a_failed_follow_status_api_uses_the_profile_follower_flag(): void
    {
        Sleep::fake();
        config(['zernio.api_key' => 'test-zernio-key']);

        Http::fake([
            '*/follow-status/*' => Http::response(['error' => 'server_error'], 500),
            '*' => Http::response(['success' => true], 200),
        ]);

        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);

        $automation = $this->automation();

        $result = app(ActionExecutor::class)->enforceFollowGate($automation, [
            'trigger' => 'direct_message',
            'user_id' => 'ig_user_follower',
            'conversation_id' => 'conv_follower',
            'instagram_profile' => ['isFollower' => true],
        ]);

        $this->assertFalse($result);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/messages'));
    }

    public function test_an_unknown_follow_status_sends_the_unknown_message_not_the_must_follow_text(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);

        Http::fake([
            '*/follow-status/*' => Http::response(['isFollower' => null], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);

        $automation = $this->automation();

        $this->assertTrue(app(ActionExecutor::class)->enforceFollowGate($automation, [
            'trigger' => 'direct_message',
            'user_id' => 'ig_user_unknown',
            'conversation_id' => 'conv_unknown',
        ]));

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/messages')) {
                return false;
            }

            $message = (string) ($request->data()['message'] ?? '');

            return ! str_contains($message, 'حتماً باید')
                && str_contains($message, 'وضعیت فالو قابل بررسی نیست');
        });
    }

    public function test_an_unknown_audience_reaches_the_gate_for_a_keywordless_message(): void
    {
        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);

        $automation = $this->automation();
        $automation->update(['audience' => [
            'followerStatus' => 'follower',
            'whenUnknown' => 'verify',
        ]]);

        $result = $this->evaluate($automation, 'سلام', [
            'instagram_profile' => ['isFollower' => null],
        ]);

        $this->assertTrue($result['pass']);
        $this->assertTrue($result['gate_only']);
        $this->assertTrue($result['audience']['pass']);
    }

    public function test_story_reply_automations_ignore_the_stale_per_automation_gate_flag(): void
    {
        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);

        $automation = $this->automation();
        // PanelController stores false for story replies; it must not disable the global gate.
        $automation->update([
            'trigger' => AutomationTrigger::StoryReply,
            'settings' => ['followGateEnabled' => false],
        ]);

        $this->assertTrue(FollowGate::enabled($automation));

        $result = $this->evaluate($automation, 'سلام', [
            'trigger' => 'story_reply',
            'instagram_profile' => ['isFollower' => null],
        ]);

        $this->assertTrue($result['pass']);
        $this->assertTrue($result['gate_only']);
    }

    public function test_the_follow_gate_uses_the_per_automation_copy_for_comments(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);

        Http::fake([
            '*/follow-status/*' => Http::response(['isFollower' => null], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);

        $automation = $this->automation();
        $automation->update([
            'trigger' => AutomationTrigger::Comment,
            'settings' => ['followGate' => [
                'message' => 'CUSTOM MUST FOLLOW TEXT',
                'unknownMessage' => 'CUSTOM GATE TEXT',
                'notFollowingMessage' => 'CUSTOM NOT FOLLOWING TEXT',
                'buttonLabel' => 'CUSTOM BUTTON',
            ]],
        ]);

        $this->assertTrue(app(ActionExecutor::class)->enforceFollowGate($automation, [
            'trigger' => 'comment',
            'user_id' => 'ig_user_custom',
            'conversation_id' => 'conv_custom',
        ]));

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), '/messages')) {
                return false;
            }

            $message = (string) ($request->data()['message'] ?? '');

            // Unknown status must send the unknown copy, never the must-follow text.
            return str_contains($message, 'CUSTOM GATE TEXT')
                && ! str_contains($message, 'CUSTOM MUST FOLLOW TEXT')
                && (string) data_get($request->data(), 'buttons.0.title') === 'CUSTOM BUTTON';
        });
    }

    public function test_story_reply_automations_use_the_global_gate_copy(): void
    {
        AppSetting::setValue('follow_gate_message', 'GLOBAL GATE TEXT');

        $automation = $this->automation();
        $automation->update([
            'trigger' => AutomationTrigger::StoryReply,
            'settings' => ['followGate' => ['message' => 'STORY CUSTOM TEXT']],
        ]);

        $this->assertSame('GLOBAL GATE TEXT', FollowGate::config($automation)['message']);
    }

    public function test_a_known_non_follower_reaches_the_gate_for_a_keywordless_message(): void
    {
        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);

        $automation = $this->automation();
        $automation->update(['audience' => [
            'followerStatus' => 'follower',
            'whenUnknown' => 'verify',
        ]]);

        $result = $this->evaluate($automation, 'سلام', [
            'instagram_profile' => ['isFollower' => false],
        ]);

        $this->assertTrue($result['pass']);
        $this->assertTrue($result['gate_only']);
        $this->assertTrue($result['audience']['pass']);
    }

    public function test_a_known_non_follower_is_told_to_follow_and_the_main_dm_is_held(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);

        Http::fake([
            '*/follow-status/*' => Http::response(['isFollower' => false], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        AppSetting::setValue('follow_gate_enabled', true);
        AppSetting::setValue('follow_gate_without_keywords', true);
        AppSetting::setValue('follow_gate_not_following_message', 'FOLLOW-FIRST');

        $automation = $this->automation();
        $automation->update(['audience' => [
            'followerStatus' => 'follower',
            'whenUnknown' => 'verify',
        ]]);

        // A keyword match must still pass the engine so the executor can gate it.
        $result = $this->evaluate($automation, 'قیمت', [
            'instagram_profile' => ['isFollower' => false],
        ]);
        $this->assertTrue($result['pass']);
        $this->assertFalse($result['gate_only']);

        $action = app(ActionExecutor::class)->execute($automation, [
            'action' => 'direct_message',
            'config' => ['message' => 'MAIN DM'],
        ], [
            'trigger' => 'direct_message',
            'user_id' => 'ig_user_nonfollower',
            'conversation_id' => 'conv_nonfollower',
            'instagram_profile' => ['isFollower' => false],
        ]);

        $this->assertSame('waiting_follow', $action['status']);

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/messages')
            && str_contains((string) ($request->data()['message'] ?? ''), 'FOLLOW-FIRST'));

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), '/messages')
            && str_contains((string) ($request->data()['message'] ?? ''), 'MAIN DM'));
    }
}
