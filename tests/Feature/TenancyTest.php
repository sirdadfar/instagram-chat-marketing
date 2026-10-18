<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\InstagramAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    private function panelUser(User $user): static
    {
        return $this->actingAs($user)->withSession(['panel_authenticated' => true]);
    }

    private function automationFor(User $owner): Automation
    {
        $account = InstagramAccount::create([
            'user_id' => $owner->id,
            'zernio_account_id' => 'acc_'.$owner->id,
            'username' => 'page_'.$owner->id,
            'status' => 'active',
        ]);

        return Automation::create([
            'instagram_account_id' => $account->id,
            'name' => 'Owner automation',
            'trigger' => 'comment',
            'status' => 'active',
        ]);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_a_user_cannot_open_another_users_automation(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $automation = $this->automationFor($owner);

        $this->panelUser($other)
            ->get(route('automations.edit', $automation))
            ->assertNotFound();
    }

    public function test_a_user_cannot_see_another_users_automation_in_the_list(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->automationFor($owner);

        $response = $this->panelUser($other)->get(route('automations.index'));

        $response->assertOk();
        $this->assertStringNotContainsString('Owner automation', $response->getContent());
    }

    public function test_an_admin_can_open_any_automation(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $automation = $this->automationFor($owner);

        $this->panelUser($admin)
            ->get(route('automations.edit', $automation))
            ->assertOk();
    }

    public function test_the_admin_area_is_forbidden_for_non_admins(): void
    {
        $user = User::factory()->create();

        $this->panelUser($user)->get(route('admin.users'))->assertForbidden();
    }

    public function test_the_admin_area_is_available_to_admins(): void
    {
        $admin = User::factory()->admin()->create();

        $this->panelUser($admin)->get(route('admin.users'))->assertOk();
    }
}
