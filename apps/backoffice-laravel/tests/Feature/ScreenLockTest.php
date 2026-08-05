<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesAdminUser;
use Tests\TestCase;

class ScreenLockTest extends TestCase
{
    use RefreshDatabase;
    use CreatesAdminUser;

    private function user(array $overrides = []): User
    {
        return $this->createAdminUser($overrides);
    }

    public function test_manual_lock_stores_flag_and_return_path_and_redirects_to_lock_screen(): void
    {
        $response = $this->actingAs($this->user())
            ->post(route('screen-lock.lock'), ['redirect_url' => '/dashboard']);

        $response->assertRedirect(route('screen-lock.show'));
        $this->assertTrue(session('screen_lock.locked'));
        $this->assertSame('/dashboard', session('screen_lock.return_to'));
    }

    public function test_locked_session_blocks_protected_routes(): void
    {
        $response = $this->actingAs($this->user())
            ->withSession(['screen_lock' => ['locked' => true]])
            ->get(route('dashboard.index'));

        $response->assertRedirect(route('screen-lock.show'));
    }

    public function test_locked_session_still_allows_lock_screen_and_logout(): void
    {
        $this->actingAs($this->user())
            ->withSession(['screen_lock' => ['locked' => true]])
            ->get(route('screen-lock.show'))
            ->assertOk();

        $this->actingAs($this->user())
            ->withSession(['screen_lock' => ['locked' => true]])
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_unlock_with_correct_password_clears_flag_and_redirects_to_saved_path(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)
            ->withSession(['screen_lock' => ['locked' => true, 'return_to' => '/dashboard']])
            ->post(route('screen-lock.unlock'), ['password' => 'secret']);

        $response->assertRedirect(url('/dashboard'));
        $this->assertFalse(session('screen_lock.locked', false));
    }

    public function test_unlock_with_incorrect_password_keeps_locked(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)
            ->withSession(['screen_lock' => ['locked' => true]])
            ->post(route('screen-lock.unlock'), ['password' => 'wrong-password']);

        $response->assertSessionHasErrors('password');
        $this->assertTrue(session('screen_lock.locked'));

        $this->actingAs($user)
            ->withSession(['screen_lock' => ['locked' => true]])
            ->get(route('dashboard.index'))
            ->assertRedirect(route('screen-lock.show'));
    }

    public function test_unlock_is_rate_limited_after_repeated_failures(): void
    {
        $user = $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($user)
                ->withSession(['screen_lock' => ['locked' => true]])
                ->post(route('screen-lock.unlock'), ['password' => 'wrong-password']);
        }

        $response = $this->actingAs($user)
            ->withSession(['screen_lock' => ['locked' => true]])
            ->post(route('screen-lock.unlock'), ['password' => 'wrong-password']);

        $response->assertStatus(429);
    }

    public function test_lock_rejects_external_redirect_url(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post(route('screen-lock.lock'), ['redirect_url' => 'https://evil.com']);

        $this->assertNull(session('screen_lock.return_to'));

        $response = $this->actingAs($user)
            ->withSession(['screen_lock' => ['locked' => true, 'return_to' => '//evil.com']])
            ->post(route('screen-lock.unlock'), ['password' => 'secret']);

        $response->assertRedirect(route('dashboard.index'));
    }

    public function test_screen_lock_show_redirects_when_not_actually_locked(): void
    {
        $response = $this->actingAs($this->user())->get(route('screen-lock.show'));

        $response->assertRedirect(route('dashboard.index'));
    }

    public function test_user_can_update_personal_idle_timeout(): void
    {
        $user = $this->user();

        $response = $this->actingAs($user)
            ->put(route('user.settings.preferences'), ['screen_lock_idle_minutes' => 5]);

        $response->assertRedirect();
        $this->assertSame(5, $user->fresh()->screen_lock_idle_minutes);
    }

    public function test_updating_idle_timeout_does_not_affect_other_users(): void
    {
        $user = $this->user();
        $other = $this->user();

        $this->actingAs($user)->put(route('user.settings.preferences'), ['screen_lock_idle_minutes' => 3]);

        $this->assertNull($other->fresh()->screen_lock_idle_minutes);
    }
}
