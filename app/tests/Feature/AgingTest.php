<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use App\Services\SubmissionIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AgingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'aging.turnstile_secret' => 'test-secret',
            'aging.turnstile_hostname' => 'eventos.huesped.org.ar',
            'aging.turnstile_iframe_hostname' => 'aging.huesped.org.ar',
        ]);
        Http::preventStrayRequests();

    }

    public function test_public_submission_persists_question_and_returns_201(): void
    {
        $this->useTrustedIdentity();

        $response = $this->postJson('/api/questions', $this->submission());

        $response->assertCreated()->assertJsonPath('retry_after', 0);
        $this->assertDatabaseHas('questions', [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'question' => '¿Cómo podemos envejecer mejor?',
            'selected' => false,
            'answered' => false,
        ]);
    }

    public function test_public_form_can_be_embedded_by_the_event_site_without_login(): void
    {
        $this->get('/formulario')->assertOk()
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://eventos.huesped.org.ar")
            ->assertSee('<meta name="viewport"', false)
            ->assertSee('Hacé tu pregunta');
    }

    public function test_submission_from_the_iframe_hostname_persists_question(): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true, 'hostname' => 'aging.huesped.org.ar', 'action' => 'question',
            ]),
        ]);

        $this->postJson('/api/questions', $this->submission())->assertCreated();

        $this->assertDatabaseHas('questions', ['email' => 'ana@example.com']);
    }

    public function test_submission_from_an_unapproved_hostname_returns_422(): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true, 'hostname' => 'unrelated.example', 'action' => 'question',
            ]),
        ]);

        $this->postJson('/api/questions', $this->submission())->assertUnprocessable();

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_iframe_submission_with_an_unexpected_action_returns_422(): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true, 'hostname' => 'aging.huesped.org.ar', 'action' => 'login',
            ]),
        ]);

        $this->postJson('/api/questions', $this->submission())->assertUnprocessable();

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_third_submission_returns_429_until_cooldown_expires(): void
    {
        $this->freezeTime();
        $this->useTrustedIdentity();
        $this->postJson('/api/questions', $this->submission())
            ->assertCreated()->assertJsonPath('retry_after', 0);
        $this->postJson('/api/questions', $this->submission())
            ->assertCreated()->assertJsonPath('retry_after', 30);

        $this->postJson('/api/questions', $this->submission())
            ->assertStatus(429)
            ->assertHeader('Retry-After', '30')
            ->assertJsonPath('retry_after', 30);

        $this->assertDatabaseCount('questions', 2);
        $this->travel(29)->seconds();
        $this->postJson('/api/questions', $this->submission())
            ->assertStatus(429)->assertJsonPath('retry_after', 1);
        $this->assertDatabaseCount('questions', 2);

        $this->travel(1)->seconds();
        $this->postJson('/api/questions', $this->submission())
            ->assertCreated()->assertJsonPath('retry_after', 0);
        $this->postJson('/api/questions', $this->submission())
            ->assertCreated()->assertJsonPath('retry_after', 30);
        $this->assertDatabaseCount('questions', 4);
    }

    public function test_cooldown_lasts_30_seconds_after_the_second_accepted_submission(): void
    {
        $this->freezeTime();
        $this->useTrustedIdentity();
        $this->postJson('/api/questions', $this->submission())->assertCreated();

        $this->travel(20)->seconds();
        $this->postJson('/api/questions', $this->submission())
            ->assertCreated()->assertJsonPath('retry_after', 30);

        $this->travel(10)->seconds();
        $this->postJson('/api/questions', $this->submission())
            ->assertStatus(429)->assertJsonPath('retry_after', 20);
        $this->assertDatabaseCount('questions', 2);
    }

    public function test_a_single_submission_expires_after_30_seconds_without_starting_a_cooldown(): void
    {
        $this->freezeTime();
        $this->useTrustedIdentity();
        $this->postJson('/api/questions', $this->submission())->assertCreated();

        $this->travel(31)->seconds();
        $this->postJson('/api/questions', $this->submission())
            ->assertCreated()->assertJsonPath('retry_after', 0);
        $this->assertDatabaseCount('questions', 2);
    }

    public function test_submission_without_turnstile_configuration_returns_503(): void
    {
        config(['aging.turnstile_secret' => null]);
        $this->postJson('/api/questions', $this->submission())->assertStatus(503);

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_invalid_turnstile_token_returns_422_without_persisting(): void
    {
        $this->useTrustedIdentity(false);

        $this->postJson('/api/questions', $this->submission())->assertUnprocessable();

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_submission_requires_valid_public_fields(): void
    {
        $this->useTrustedIdentity();

        $this->postJson('/api/questions', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'question', 'cf-turnstile-response']);

        $this->assertDatabaseCount('questions', 0);
    }

    public function test_public_submission_cannot_select_or_answer_a_question(): void
    {
        $this->useTrustedIdentity();

        $this->postJson('/api/questions', [...$this->submission(), 'selected' => true, 'answered' => true])
            ->assertCreated();

        $this->assertDatabaseHas('questions', ['selected' => false, 'answered' => false]);
    }

    public function test_panel_and_its_data_require_authentication(): void
    {
        $this->get('/panel')->assertRedirect('/login');
        $this->getJson('/panel/data')->assertUnauthorized();
        $this->patchJson('/panel/questions/1', ['selected' => true])->assertUnauthorized();
    }

    public function test_authenticated_moderator_can_open_panel(): void
    {
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]))->get('/panel')->assertOk();
    }

    public function test_moderator_can_select_answer_and_undo_both_changes(): void
    {
        $question = $this->createQuestion();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->patchJson('/panel/questions/'.$question->id, ['selected' => true])->assertSuccessful();
        $this->assertTrue($question->fresh()->selected);
        $this->patchJson('/panel/questions/'.$question->id, ['answered' => true])->assertSuccessful();
        $this->assertTrue($question->fresh()->answered);
        $this->patchJson('/panel/questions/'.$question->id, ['selected' => false, 'answered' => false])->assertSuccessful();

        $this->assertFalse($question->fresh()->selected);
        $this->assertFalse($question->fresh()->answered);
    }

    public function test_moderation_rejects_non_boolean_flags(): void
    {
        $question = $this->createQuestion();

        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]))
            ->patchJson('/panel/questions/'.$question->id, ['selected' => 'invalid', 'answered' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['selected', 'answered']);

        $this->assertFalse($question->fresh()->selected);
        $this->assertFalse($question->fresh()->answered);
    }

    public function test_panel_paginates_received_questions_and_keeps_answered_rows_in_place(): void
    {
        $questions = collect(range(1, 21))->map(fn (): Question => $this->createQuestion());
        $questions->first()->update(['answered' => true]);
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->getJson('/panel/data')->assertOk()
            ->assertJsonCount(20, 'received.data')
            ->assertJsonPath('received.total', 21)
            ->assertJsonPath('received.data.0.id', $questions->first()->id);
        $this->getJson('/panel/data?page=2')->assertOk()
            ->assertJsonCount(1, 'received.data')
            ->assertJsonPath('received.data.0.id', $questions->last()->id);
    }

    public function test_panel_separates_active_selected_and_answered_questions(): void
    {
        $selected = $this->createQuestion();
        $selected->update(['selected' => true]);
        $answered = $this->createQuestion();
        $answered->update(['selected' => true, 'answered' => true]);
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->getJson('/panel/data')->assertOk()
            ->assertJsonCount(1, 'selected')->assertJsonPath('selected.0.id', $selected->id);
        $this->getJson('/panel/data?answered=1')->assertOk()
            ->assertJsonCount(1, 'answered.data')->assertJsonPath('answered.data.0.id', $answered->id);
    }

    public function test_selecting_removes_a_question_from_received_and_deselecting_returns_it(): void
    {
        $question = $this->createQuestion();
        $other = $this->createQuestion();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->patchJson('/panel/questions/'.$question->id, ['selected' => true])->assertOk();

        $this->assertDatabaseHas('questions', ['id' => $question->id, 'selected' => true]);
        $this->getJson('/panel/data')->assertOk()
            ->assertJsonPath('received.total', 1)
            ->assertJsonPath('received.data.0.id', $other->id)
            ->assertJsonCount(1, 'selected')
            ->assertJsonPath('selected.0.id', $question->id);

        $this->patchJson('/panel/questions/'.$question->id, ['selected' => false])->assertOk();

        $this->assertDatabaseHas('questions', ['id' => $question->id, 'selected' => false, 'answered' => false]);
        $this->getJson('/panel/data')->assertOk()
            ->assertJsonPath('received.total', 2)
            ->assertJsonPath('received.data.0.id', $question->id)
            ->assertJsonPath('received.data.1.id', $other->id)
            ->assertJsonCount(0, 'selected');
    }

    #[TestWith(['asc', 0, 19, 20])]
    #[TestWith(['desc', 20, 1, 0])]
    public function test_received_sort_applies_before_pagination_and_excludes_selected_and_discarded(string $order, int $firstIndex, int $lastIndex, int $secondPageIndex): void
    {
        $questions = collect(range(1, 21))->map(fn (): Question => $this->createQuestion());
        $selected = $this->createQuestion();
        $selected->update(['selected' => true]);
        $discarded = $this->createQuestion();
        $discarded->delete();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->getJson('/panel/data?order='.$order)->assertOk()
            ->assertJsonCount(20, 'received.data')
            ->assertJsonPath('received.total', 21)
            ->assertJsonPath('received.data.0.id', $questions->get($firstIndex)->id)
            ->assertJsonPath('received.data.19.id', $questions->get($lastIndex)->id)
            ->assertJsonCount(1, 'selected')
            ->assertJsonPath('selected.0.id', $selected->id)
            ->assertJsonPath('discarded_count', 1);
        $this->getJson('/panel/data?order='.$order.'&page=2')->assertOk()
            ->assertJsonCount(1, 'received.data')
            ->assertJsonPath('received.data.0.id', $questions->get($secondPageIndex)->id);
    }

    public function test_received_sort_rejects_an_unknown_order(): void
    {
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->getJson('/panel/data?order=invalid')->assertUnprocessable()->assertJsonValidationErrors('order');
    }

    public function test_cors_allows_the_event_site_to_submit_questions(): void
    {
        $this->withHeaders([
            'Origin' => 'https://eventos.huesped.org.ar',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type',
        ])->options('/api/questions')->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', 'https://eventos.huesped.org.ar');
    }

    public function test_cors_does_not_allow_an_unrelated_origin(): void
    {
        $this->withHeaders([
            'Origin' => 'https://unrelated.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/api/questions')->assertHeader('Access-Control-Allow-Origin', 'https://eventos.huesped.org.ar');
    }

    public function test_rate_limit_uses_ip_not_email_or_forwarded_headers(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'eventos.huesped.org.ar', 'action' => 'question'])]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1'])->postJson('/api/questions', $this->submission())->assertCreated();
        $this->withHeader('X-Forwarded-For', '192.0.2.99')->postJson('/api/questions', [...$this->submission(), 'email' => 'other@example.com'])->assertCreated();
        $this->withHeader('X-Forwarded-For', '192.0.2.100')->postJson('/api/questions', [...$this->submission(), 'email' => 'third@example.com'])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->postJson('/api/questions', $this->submission())->assertCreated();
        $this->assertDatabaseCount('questions', 3);
    }

    public function test_dummy_turnstile_key_is_rejected_outside_local(): void
    {
        config(['aging.turnstile_secret' => '1x0000000000000000000000000000000AA']);
        $this->postJson('/api/questions', $this->submission())->assertStatus(503);
        Http::assertNothingSent();
    }

    public function test_local_dummy_turnstile_response_is_accepted(): void
    {
        $this->app->instance('env', 'local');
        config(['aging.turnstile_secret' => '1x0000000000000000000000000000000AA']);
        Http::fake(['*' => Http::response(['success' => true, 'hostname' => 'example.com'])]);
        $this->postJson('/api/questions', $this->submission())->assertCreated();
    }

    private function useTrustedIdentity(bool $captchaValid = true): void
    {
        Http::fake([
            'https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => $captchaValid,
                'hostname' => 'eventos.huesped.org.ar',
                'action' => 'question',
            ]),
        ]);
        $this->app->instance(SubmissionIdentity::class, new class extends SubmissionIdentity
        {
            public function resolve(Request $request): ?string
            {
                return 'test-attendee';
            }
        });
    }

    /** @return array<string, string> */
    private function submission(): array
    {
        return [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'question' => '¿Cómo podemos envejecer mejor?',
            'cf-turnstile-response' => 'test-token',
        ];
    }

    private function createQuestion(): Question
    {
        return Question::create([
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'question' => '¿Cómo podemos envejecer mejor?',
        ]);
    }
}
