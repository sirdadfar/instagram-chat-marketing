<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\InstagramAccount;
use App\Models\User;
use App\Services\Automation\ActionExecutor;
use App\Services\Automation\ZernioAutomationSync;
use App\Support\Credentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZernioCredentialPinningTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): array
    {
        $user = User::factory()->create();
        Credentials::rememberUserKey($user, 'tenant-key-123');

        $account = InstagramAccount::create([
            'user_id' => $user->id,
            'zernio_account_id' => 'acc_tenant',
            'username' => 'tenant_page',
            'status' => 'active',
            'metadata' => ['profileId' => 'profile_tenant'],
        ]);

        return [$user, $account];
    }

    private function automation(InstagramAccount $account, string $trigger = 'comment'): Automation
    {
        return Automation::create([
            'instagram_account_id' => $account->id,
            'name' => 'Pinned automation',
            'trigger' => $trigger,
            'status' => 'active',
            'execution_mode' => 'zernio',
            'settings' => ['followGateEnabled' => false],
            'audience' => ['followerStatus' => 'non_follower', 'whenUnknown' => 'send'],
        ]);
    }

    public function test_native_sync_uses_the_page_owners_key(): void
    {
        [$user, $account] = $this->tenant();
        $automation = $this->automation($account);

        config([
            'zernio.base_url' => 'https://zernio.test/api/v1',
            'zernio.api_key' => 'server-key-must-not-be-used',
        ]);

        Http::fake([
            '*' => Http::response(['id' => 'remote_1'], 200),
        ]);

        app(ZernioAutomationSync::class)->sync($automation);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tenant-key-123'));
    }

    public function test_native_sync_accepts_a_profile_object_as_the_profile_id(): void
    {
        [$user, $account] = $this->tenant();
        $account->update(['metadata' => []]);
        $automation = $this->automation($account);

        config([
            'zernio.base_url' => 'https://zernio.test/api/v1',
            'zernio.api_key' => 'server-key-must-not-be-used',
        ]);

        Http::fake([
            '*/accounts*' => Http::response([
                'accounts' => [[
                    '_id' => 'acc_tenant',
                    // Older/looser API versions return the whole profile object.
                    'profileId' => ['_id' => 'profile_from_object'],
                ]],
            ], 200),
            '*' => Http::response(['id' => 'remote_1'], 200),
        ]);

        app(ZernioAutomationSync::class)->sync($automation);

        Http::assertSent(fn ($request) => str_ends_with((string) parse_url($request->url(), PHP_URL_PATH), '/comment-automations')
            && (string) data_get($request->data(), 'profileId') === 'profile_from_object');
    }

    public function test_action_executor_uses_the_pinned_users_key(): void
    {
        [$user, $account] = $this->tenant();
        $automation = $this->automation($account, 'direct_message');

        config([
            'zernio.base_url' => 'https://zernio.test/api/v1',
            'zernio.api_key' => 'server-key-must-not-be-used',
        ]);

        Http::fake([
            '*' => Http::response(['data' => ['messageId' => 'm1']], 200),
        ]);

        $result = app(ActionExecutor::class)
            ->forUser($user)
            ->execute($automation, [
                'action' => 'direct_message',
                'config' => ['message' => 'hello'],
            ], [
                'conversation_id' => 'conv_1',
                'user_id' => 'u1',
            ]);

        $this->assertSame('sent', $result['status']);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tenant-key-123'));
    }
}
