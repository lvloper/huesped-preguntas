<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use App\Services\SubmissionIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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
        ]);
        Http::preventStrayRequests();

    }

    public function test_public_submission_persists_question_and_returns_201(): void
    {
        $this->useTrustedIdentity();

        $response = $this->postJson('/api/questions', $this->submission());

        $response->assertCreated()->assertJsonPath('retry_after', 30);
        $this->assertDatabaseHas('questions', [
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'question' => '¿Cómo podemos envejecer mejor?',
            'selected' => false,
            'answered' => false,
        ]);
    }

    public function test_repeated_submission_returns_429_until_cooldown_expires(): void
    {
        $this->freezeTime();
        $this->useTrustedIdentity();
        $this->postJson('/api/questions', $this->submission())->assertCreated();

        $this->postJson('/api/questions', $this->submission())
            ->assertStatus(429)
            ->assertJsonPath('retry_after', 30);

        $this->assertDatabaseCount('questions', 1);
        $this->travel(31)->seconds();
        $this->postJson('/api/questions', $this->submission())->assertCreated();
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
        $this->withHeader('X-Forwarded-For', '192.0.2.99')->postJson('/api/questions', [...$this->submission(), 'email' => 'other@example.com'])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2'])->postJson('/api/questions', $this->submission())->assertCreated();
        $this->assertDatabaseCount('questions', 2);
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
