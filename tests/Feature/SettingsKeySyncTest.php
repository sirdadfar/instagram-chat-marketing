<?php

namespace Tests\Feature;

use App\Models\InstagramAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsKeySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_a_personal_key_moves_a_legacy_page_to_the_key_owner(): void
    {
        Http::fake([
            '*' => Http::response([
                'accounts' => [[
                    '_id' => 'zernio_acc_1',
                    'platform' => 'instagram',
                    'username' => 'page_owner',
                    'displayName' => 'Page Owner',
                    'platformStatus' => 'active',
                ]],
            ], 200),
        ]);

        $admin = User::factory()->admin()->create();
        $newUser = User::factory()->create();

        // A page discovered before multi-tenancy is attached to the admin.
        $account = InstagramAccount::create([
            'user_id' => $admin->id,
            'zernio_account_id' => 'zernio_acc_1',
            'username' => 'page_owner',
            'status' => 'active',
        ]);

        $this->actingAs($newUser)
            ->withSession(['panel_authenticated' => true])
            ->post(route('settings.save'), [
                'zernio_key_card' => '1',
                'zernio_api_key' => 'new-personal-key',
                'zernio_webhook_secret' => 'new-webhook-secret',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message): bool => str_contains((string) $message, 'شناسایی شدند'));

        $this->assertSame($newUser->id, $account->fresh()->user_id);
        $this->assertSame('active', $account->fresh()->status);
        $this->assertNotNull($newUser->fresh()->zernio_api_key_enc);
    }

    public function test_saving_a_key_without_any_page_reports_it_explicitly(): void
    {
        Http::fake([
            '*' => Http::response(['accounts' => []], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['panel_authenticated' => true])
            ->post(route('settings.save'), [
                'zernio_key_card' => '1',
                'zernio_api_key' => 'personal-key',
                'zernio_webhook_secret' => 'webhook-secret',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', fn ($message): bool => str_contains((string) $message, 'هیچ پیج اینستاگرامی'));

        $this->assertSame(0, InstagramAccount::count());
        $this->assertNotNull($user->fresh()->zernio_api_key_enc);
    }
}
