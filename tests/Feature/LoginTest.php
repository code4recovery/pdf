<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoginTest extends TestCase
{
    #[Test]
    public function login_page_renders(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Login'));
    }

    #[Test]
    public function valid_credentials_log_in_and_redirect_to_usage(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'correct-password',
        ]);

        $response->assertRedirect('/usage');
        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function wrong_password_is_rejected_with_error(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function sixth_attempt_in_a_minute_is_throttled(): void
    {
        User::factory()->create([
            'email' => 'admin@example.test',
            'password' => Hash::make('correct-password'),
        ]);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'admin@example.test',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'admin@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    #[Test]
    public function logout_ends_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);
        $this->assertAuthenticatedAs($user);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    #[Test]
    public function admin_command_creates_user(): void
    {
        $this->artisan('usage:admin', ['email' => 'a@b.org'])
            ->expectsQuestion('Name', 'A')
            ->expectsQuestion('Password', str_repeat('x', 12))
            ->assertSuccessful();

        $user = User::where('email', 'a@b.org')->first();

        $this->assertNotNull($user);
        $this->assertSame('A', $user->name);
        $this->assertTrue(Hash::check(str_repeat('x', 12), $user->password));
    }

    #[Test]
    public function admin_command_updates_existing_user_password(): void
    {
        $existing = User::factory()->create([
            'email' => 'a@b.org',
            'name' => 'Old Name',
            'password' => Hash::make('old-password-here'),
        ]);

        $this->artisan('usage:admin', ['email' => 'a@b.org'])
            ->expectsQuestion('Name', 'New Name')
            ->expectsQuestion('Password', str_repeat('y', 12))
            ->assertSuccessful();

        $existing->refresh();

        $this->assertSame('New Name', $existing->name);
        $this->assertTrue(Hash::check(str_repeat('y', 12), $existing->password));
    }
}
