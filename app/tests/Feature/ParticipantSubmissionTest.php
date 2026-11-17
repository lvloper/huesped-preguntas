<?php

namespace Tests\Feature;

use App\Models\Participant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ParticipantSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['aging.turnstile_secret' => 'test-secret', 'aging.turnstile_hostname' => 'eventos.huesped.org.ar']);
        Http::preventStrayRequests();
    }

    public function test_submission_with_a_uid_uses_the_participant_data_without_manual_fields(): void
    {
        $participant = Participant::factory()->create(['uid' => '123', 'name' => 'Ana Pérez', 'email' => 'ana@example.com']);
        Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true, 'hostname' => 'eventos.huesped.org.ar', 'action' => 'question'])]);

        $this->postJson('/api/questions', ['uid' => $participant->uid, 'question' => 'Mi pregunta', 'cf-turnstile-response' => 'test-token'])
            ->assertCreated()->assertJsonPath('retry_after', 0);

        $this->assertDatabaseHas('questions', ['participant_id' => $participant->id, 'name' => 'Ana Pérez', 'email' => 'ana@example.com', 'question' => 'Mi pregunta']);
        Http::assertSentCount(1);
    }

    public function test_submission_cannot_replace_the_data_or_participant_associated_with_a_uid(): void
    {
        $participant = Participant::factory()->create(['name' => 'Nombre registrado', 'email' => 'registered@example.com']);
        $other = Participant::factory()->create();
        Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true, 'hostname' => 'eventos.huesped.org.ar', 'action' => 'question'])]);

        $this->postJson('/api/questions', ['uid' => $participant->uid, 'participant_id' => $other->id, 'name' => ['invalid'], 'email' => 'spoofed', 'question' => 'Mi pregunta', 'cf-turnstile-response' => 'test-token'])
            ->assertCreated();

        $this->assertDatabaseHas('questions', ['participant_id' => $participant->id, 'name' => 'Nombre registrado', 'email' => 'registered@example.com']);
        $this->assertDatabaseMissing('questions', ['participant_id' => $other->id]);
        Http::assertSentCount(1);
    }

    public function test_an_unknown_uid_cannot_submit_a_question_using_manual_identity_fields(): void
    {
        $this->postJson('/api/questions', ['uid' => str_repeat('Z', 40), 'name' => 'Ana', 'email' => 'ana@example.com', 'question' => 'Mi pregunta', 'cf-turnstile-response' => 'test-token'])
            ->assertUnprocessable()->assertJsonValidationErrors('uid');

        $this->assertDatabaseCount('questions', 0);
        Http::assertNothingSent();
    }

    public function test_public_submissions_without_a_uid_cannot_attach_a_participant_id(): void
    {
        $participant = Participant::factory()->create();
        Http::fake(['https://challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response(['success' => true, 'hostname' => 'eventos.huesped.org.ar', 'action' => 'question'])]);

        $this->postJson('/api/questions', ['participant_id' => $participant->id, 'name' => 'Manual', 'email' => 'manual@example.com', 'question' => 'Mi pregunta', 'cf-turnstile-response' => 'test-token'])
            ->assertCreated();

        $this->assertDatabaseHas('questions', ['participant_id' => null, 'name' => 'Manual', 'email' => 'manual@example.com']);
        Http::assertSentCount(1);
    }
}
