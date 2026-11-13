<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders(): void
    {
        $this->withoutVite()->get(route('admin.login'))->assertOk()->assertSee('Admin sign in');
    }

    public function test_admin_can_sign_in(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->post(route('admin.login.store'), ['login' => $admin->email, 'password' => 'password']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_sign_in_with_their_username(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'admin']);

        $response = $this->post(route('admin.login.store'), ['login' => 'admin', 'password' => 'password']);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->from(route('admin.login'))->post(route('admin.login.store'), ['login' => $admin->email, 'password' => 'wrong-password']);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_users_who_are_not_admins_cannot_sign_in(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('admin.login.store'), ['login' => $user->email, 'password' => 'password']);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_sign_in_is_locked_after_five_failed_attempts(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (range(1, 5) as $attempt) {
            $this->post(route('admin.login.store'), ['login' => $admin->email, 'password' => 'wrong-password']);
        }

        $response = $this->post(route('admin.login.store'), ['login' => $admin->email, 'password' => 'password']);

        $response->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_signed_in_users_who_are_not_admins_are_forbidden(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_admin_can_log_out(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->assertGuest();
    }
}
