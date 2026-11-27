<?php

namespace Tests\Feature;

use App\Models\Run;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_a_user_with_a_hashed_password(): void
    {
        $this->postJson(route('register'), [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertOk()->assertJson(['success' => true, 'redirect' => route('login.view')]);

        $user = User::sole();
        $this->assertTrue(Hash::check('secret-password', $user->password));
    }

    public function test_register_validates_input(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson(route('register'), [
            'name' => '',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('register'), ['name' => ''])->assertUnprocessable();
        }

        $this->postJson(route('register'), ['name' => ''])->assertTooManyRequests();
    }

    public function test_login_with_valid_credentials(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJson(['success' => true, 'redirect' => route('menu.view')]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_a_wrong_password_fails(): void
    {
        $user = User::factory()->create();

        $this->postJson(route('login'), ['email' => $user->email, 'password' => 'wrong'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

    public function test_login_locks_out_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson(route('login'), ['email' => $user->email, 'password' => 'wrong']);
        }

        // Even the right password is refused while locked out.
        $this->postJson(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', fn (string $message) => str_starts_with($message, 'Demasiados intentos.'));

        $this->assertGuest();
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())->postJson(route('logout'))->assertOk();

        $this->assertGuest();
    }

    public function test_profile_update_changes_name_and_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson(route('profile.update'), ['name' => 'Nuevo', 'email' => 'nuevo@example.com'])
            ->assertOk();

        $this->assertSame('nuevo@example.com', $user->fresh()->email);
    }

    public function test_password_change_requires_the_current_password(): void
    {
        $user = User::factory()->create();
        $payload = ['name' => $user->name, 'email' => $user->email, 'new_password' => 'new-password', 'new_password_confirmation' => 'new-password'];

        $this->actingAs($user)->putJson(route('profile.update'), $payload)
            ->assertJsonValidationErrors(['current_password']);
        $this->actingAs($user)->putJson(route('profile.update'), [...$payload, 'current_password' => 'wrong'])
            ->assertJsonValidationErrors(['current_password' => 'La contraseña actual es incorrecta']);
        $this->actingAs($user)->putJson(route('profile.update'), [...$payload, 'current_password' => 'password'])
            ->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_deleting_the_account_requires_the_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->deleteJson(route('profile.delete'), ['password' => 'wrong'])
            ->assertJsonValidationErrors(['password']);

        $this->assertModelExists($user);
    }

    public function test_a_wrong_password_reopens_the_delete_dialog_on_the_profile_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('profile.view'))
            ->delete(route('profile.delete'), ['password' => 'wrong'])
            ->assertRedirect(route('profile.view'))
            ->assertSessionHasErrors(['password'], errorBag: 'deleteAccount');

        $this->assertModelExists($user);
    }

    public function test_deleting_the_account_logs_out_and_removes_its_data(): void
    {
        $run = Run::factory()->create();

        $this->actingAs($run->user)->deleteJson(route('profile.delete'), ['password' => 'password'])->assertOk();

        $this->assertGuest();
        $this->assertModelMissing($run->user);
        $this->assertModelMissing($run);
    }

    public function test_rankings_do_not_expose_emails(): void
    {
        $run = Run::factory()->create(['words_correct' => ['BARCO'], 'score' => 10]);

        $this->actingAs($run->user)->get(route('scores.view'))->assertOk()->assertDontSee($run->user->email);
    }
}
