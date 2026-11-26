<?php

namespace Tests\Feature;

use App\Models\Automation;
use App\Models\InstagramAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationIndexTest extends TestCase
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
            'zernio_account_id' => 'acc_index_'.$owner->id,
            'username' => 'index_page_'.$owner->id,
            'status' => 'active',
        ]);
    }

    private function automation(InstagramAccount $account, string $name, string $status, string $trigger = 'comment'): Automation
    {
        return Automation::create([
            'instagram_account_id' => $account->id,
            'name' => $name,
            'trigger' => $trigger,
            'status' => $status,
        ]);
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $owner = User::factory()->create();
        $account = $this->account($owner);
        $this->automation($account, 'فعال ویژه', 'active');
        $this->automation($account, 'متوقف ویژه', 'paused');

        $this->panelUser($owner)
            ->get(route('automations.index', ['status' => 'active']))
            ->assertOk()
            ->assertSeeText('فعال ویژه')
            ->assertDontSeeText('متوقف ویژه');
    }

    public function test_the_list_can_be_searched_by_name(): void
    {
        $owner = User::factory()->create();
        $account = $this->account($owner);
        $this->automation($account, 'فروش نوروزی', 'active');
        $this->automation($account, 'پاسخ قیمت', 'active');

        $this->panelUser($owner)
            ->get(route('automations.index', ['q' => 'نوروزی']))
            ->assertOk()
            ->assertSeeText('فروش نوروزی')
            ->assertDontSeeText('پاسخ قیمت');
    }
}
