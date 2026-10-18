<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_registered_user_becomes_admin(): void
    {
        $this->post('/register', [
            'name' => 'First User',
            'username' => 'firstuser',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertTrue(User::firstOrFail()->is_admin);
    }

    public function test_second_registered_user_is_not_admin(): void
    {
        User::factory()->create();

        $this->post('/register', [
            'name' => 'Second User',
            'username' => 'seconduser',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse(User::where('username', 'seconduser')->firstOrFail()->is_admin);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->create(['username' => 'johndoe']);

        $this->post('/login', ['username' => 'johndoe', 'password' => 'password'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_returns_a_uniform_error_for_bad_credentials(): void
    {
        User::factory()->create(['username' => 'johndoe']);

        $this->from('/login')
            ->post('/login', ['username' => 'johndoe', 'password' => 'wrong-password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_suspended_user_cannot_log_in(): void
    {
        User::factory()->suspended()->create(['username' => 'blocked']);

        $this->from('/login')
            ->post('/login', ['username' => 'blocked', 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }
}
