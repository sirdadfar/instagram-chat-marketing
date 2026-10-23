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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

    private function evaluate(Automation $automation, string $text): array
    {
        AppSetting::setValue('automation_global_enabled', true);
        AppSetting::setValue('business_hours_enabled', false);

        return app(AutomationEngine::class)->evaluate($automation, $text, [
            'trigger' => 'direct_message',
            'user_id' => 'ig_user_1',
            'conversation_id' => 'conv_1',
            'instagram_profile' => ['isFollower' => false],
        ]);
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
}
