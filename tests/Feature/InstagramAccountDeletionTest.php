<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\InstagramAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function panelUser(User $user): static
    {
        return $this->actingAs($user)->withSession(['panel_authenticated' => true]);
    }

    private function account(User $owner, array $attributes = []): InstagramAccount
    {
        return InstagramAccount::create(array_merge([
            'user_id' => $owner->id,
            'zernio_account_id' => 'zernio_page_1',
            'username' => 'sample_page',
            'status' => 'active',
        ], $attributes));
    }

    public function test_owner_can_remove_a_connected_page_and_its_automations(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $owner = User::factory()->create();
        $account = $this->account($owner);
        $automation = Automation::create([
            'instagram_account_id' => $account->id,
            'name' => 'Page automation',
            'trigger' => 'comment',
            'status' => 'active',
        ]);

        $this->panelUser($owner)
            ->delete(route('settings.accounts.destroy', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('instagram_accounts', ['id' => $account->id]);
        $this->assertDatabaseMissing('automations', ['id' => $automation->id]);
    }

    public function test_user_cannot_remove_another_users_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $account = $this->account($owner, [
            'zernio_account_id' => 'zernio_page_2',
            'username' => 'private_page',
        ]);

        $this->panelUser($other)
            ->delete(route('settings.accounts.destroy', $account))
            ->assertNotFound();

        $this->assertDatabaseHas('instagram_accounts', ['id' => $account->id]);
    }

    public function test_deleting_a_page_disconnects_it_from_zernio(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $owner = User::factory()->create();
        $account = $this->account($owner, [
            'zernio_account_id' => 'zernio_page_x',
            'username' => 'disconnect_me',
        ]);

        $this->panelUser($owner)
            ->delete(route('settings.accounts.destroy', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('instagram_accounts', ['id' => $account->id]);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && str_contains($request->url(), '/accounts/zernio_page_x'));
    }

    public function test_upstream_404_still_removes_the_page_locally(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);
        Http::fake([
            '*/accounts/zernio_page_missing' => Http::response(['message' => 'Not found'], 404),
            '*' => Http::response(['success' => true], 200),
        ]);

        $owner = User::factory()->create();
        $account = $this->account($owner, [
            'zernio_account_id' => 'zernio_page_missing',
            'username' => 'already_gone',
        ]);

        $this->panelUser($owner)
            ->delete(route('settings.accounts.destroy', $account))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('instagram_accounts', ['id' => $account->id]);
    }

    public function test_inactive_page_is_listed_and_can_be_deleted(): void
    {
        config(['zernio.api_key' => null]);
        Http::fake();

        $owner = User::factory()->create();
        $account = $this->account($owner, [
            'zernio_account_id' => 'zernio_page_inactive',
            'username' => 'inactive_page',
            'status' => 'inactive',
        ]);

        $this->panelUser($owner)
            ->get(route('settings'))
            ->assertOk()
            ->assertSee('inactive_page');

        $this->panelUser($owner)
            ->delete(route('settings.accounts.destroy', $account))
            ->assertRedirect();

        $this->assertDatabaseMissing('instagram_accounts', ['id' => $account->id]);
    }

    public function test_upstream_failure_still_removes_the_page_and_reports_it(): void
    {
        config(['zernio.api_key' => 'test-zernio-key']);
        Http::fake([
            '*/accounts/zernio_page_error' => Http::response(['message' => 'Server error'], 500),
            '*' => Http::response(['success' => true], 200),
        ]);

        $owner = User::factory()->create();
        $account = $this->account($owner, [
            'zernio_account_id' => 'zernio_page_error',
            'username' => 'error_page',
        ]);

        $this->panelUser($owner)
            ->delete(route('settings.accounts.destroy', $account))
            ->assertRedirect()
            ->assertSessionHasErrors('account');

        $this->assertDatabaseMissing('instagram_accounts', ['id' => $account->id]);
    }

    public function test_deletion_still_works_without_an_api_key(): void
    {
        config(['zernio.api_key' => null]);
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $owner = User::factory()->create();
        $account = $this->account($owner, [
            'zernio_account_id' => 'zernio_page_no_key',
            'username' => 'no_key_page',
        ]);

        $this->panelUser($owner)
            ->delete(route('settings.accounts.destroy', $account))
            ->assertRedirect();

        $this->assertDatabaseMissing('instagram_accounts', ['id' => $account->id]);
        Http::assertNothingSent();
    }
}
