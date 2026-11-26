<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\InstagramAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstagramAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function panelUser(User $user): static
    {
        return $this->actingAs($user)->withSession(['panel_authenticated' => true]);
    }

    public function test_owner_can_remove_a_connected_page_and_its_automations(): void
    {
        $owner = User::factory()->create();
        $account = InstagramAccount::create([
            'user_id' => $owner->id,
            'zernio_account_id' => 'zernio_page_1',
            'username' => 'sample_page',
            'status' => 'active',
        ]);
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
        $account = InstagramAccount::create([
            'user_id' => $owner->id,
            'zernio_account_id' => 'zernio_page_2',
            'username' => 'private_page',
            'status' => 'active',
        ]);

        $this->panelUser($other)
            ->delete(route('settings.accounts.destroy', $account))
            ->assertNotFound();

        $this->assertDatabaseHas('instagram_accounts', ['id' => $account->id]);
    }
}
