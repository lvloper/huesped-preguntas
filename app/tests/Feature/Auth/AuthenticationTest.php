<?php

namespace Tests\Feature\Auth;

use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create(['email' => User::PANEL_EMAIL]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create(['email' => User::PANEL_EMAIL]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_other_accounts_cannot_login_even_with_valid_password(): void
    {
        $user = User::factory()->create(['email' => 'pruebas@aging.test']);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_existing_other_account_session_cannot_access_any_panel_route(): void
    {
        $user = User::factory()->create();
        $question = Question::create(['name' => 'Prueba', 'email' => 'prueba@example.com', 'question' => 'Pregunta']);
        $this->actingAs($user)->get('/panel')->assertForbidden();
        $this->getJson('/panel/data')->assertForbidden();
        $this->getJson('/panel/data?answered=1')->assertForbidden();
        $this->patchJson('/panel/questions/'.$question->id, ['selected' => true])->assertForbidden();
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'selected' => false]);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create(['email' => User::PANEL_EMAIL]);

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
