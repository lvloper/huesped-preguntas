<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class QuestionDiscardTest extends TestCase
{
    use RefreshDatabase;

    public function test_discarded_questions_and_discard_action_require_authentication(): void
    {
        $question = $this->createQuestion();

        $this->getJson('/panel/data?discarded=1')->assertUnauthorized();
        $this->deleteJson('/panel/questions/'.$question->id)->assertUnauthorized();
        $this->patchJson('/panel/questions/'.$question->id.'/restore')->assertUnauthorized();

        $this->assertNotSoftDeleted($question);
    }

    public function test_another_account_cannot_read_discarded_questions_or_discard(): void
    {
        $question = $this->createQuestion();
        $this->actingAs(User::factory()->create());

        $this->getJson('/panel/data?discarded=1')->assertForbidden();
        $this->deleteJson('/panel/questions/'.$question->id)->assertForbidden();
        $this->patchJson('/panel/questions/'.$question->id.'/restore')->assertForbidden();

        $this->assertNotSoftDeleted($question);
    }

    #[TestWith([false, false])]
    #[TestWith([true, false])]
    #[TestWith([false, true])]
    #[TestWith([true, true])]
    public function test_moderator_can_move_a_question_to_discarded_from_any_state(bool $selected, bool $answered): void
    {
        $question = $this->createQuestion();
        $question->update(['selected' => $selected, 'answered' => $answered]);
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->deleteJson('/panel/questions/'.$question->id)->assertNoContent();

        $this->assertSoftDeleted($question);
        $this->getJson('/panel/data')->assertOk()
            ->assertJsonCount(0, 'received.data')
            ->assertJsonCount(0, 'selected')
            ->assertJsonPath('discarded_count', 1);
        $this->getJson('/panel/data?answered=1')->assertOk()->assertJsonCount(0, 'answered.data');
        $this->getJson('/panel/data?discarded=1')->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(1, 'discarded.data')
            ->assertJsonPath('discarded.data.0.id', $question->id)
            ->assertJsonPath('discarded.data.0.name', $question->name)
            ->assertJsonPath('discarded.data.0.email', $question->email)
            ->assertJsonPath('discarded.data.0.question', $question->question);
    }

    public function test_discarded_questions_are_paginated_with_the_most_recent_discard_first(): void
    {
        $this->freezeTime();
        $questions = collect(range(1, 21))->map(fn (): Question => $this->createQuestion());
        foreach ($questions->reverse() as $question) {
            $question->delete();
            $this->travel(1)->seconds();
        }
        $active = $this->createQuestion();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->getJson('/panel/data?discarded=1')->assertOk()
            ->assertJsonCount(20, 'discarded.data')
            ->assertJsonPath('discarded.total', 21)
            ->assertJsonPath('discarded.data.0.id', $questions->first()->id);
        $this->getJson('/panel/data?discarded=1&page=2')->assertOk()
            ->assertJsonCount(1, 'discarded.data')
            ->assertJsonPath('discarded.data.0.id', $questions->last()->id);
        $this->getJson('/panel/data')->assertOk()
            ->assertJsonPath('received.total', 1)
            ->assertJsonPath('received.data.0.id', $active->id)
            ->assertJsonPath('discarded_count', 21);
    }

    public function test_reaching_100_discards_keeps_every_discarded_question(): void
    {
        $this->freezeTime();
        $first = $this->createQuestion();
        $first->delete();
        foreach (range(1, 98) as $index) {
            $this->createQuestion()->delete();
        }
        $last = $this->createQuestion();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->deleteJson('/panel/questions/'.$last->id)->assertNoContent();

        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($last);
        $this->assertDatabaseCount('questions', 100);
        $this->getJson('/panel/data')->assertOk()->assertJsonPath('discarded_count', 100);
    }

    public function test_every_discard_over_100_permanently_removes_the_oldest_discard(): void
    {
        $this->freezeTime();
        $questions = collect(range(1, 100))->map(fn (): Question => $this->createQuestion());
        foreach ($questions->reverse() as $question) {
            $question->delete();
            $this->travel(1)->seconds();
        }
        $active = $this->createQuestion();
        $active->update(['selected' => true, 'answered' => true]);
        $next = $this->createQuestion();
        $last = $this->createQuestion();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->deleteJson('/panel/questions/'.$next->id)->assertNoContent();

        $this->assertDatabaseMissing('questions', ['id' => $questions->last()->id]);
        $this->assertSoftDeleted($questions->first());
        $this->assertSoftDeleted($next);
        $this->assertNotSoftDeleted($active);
        $this->getJson('/panel/data?discarded=1')->assertOk()
            ->assertJsonPath('discarded.total', 100)
            ->assertJsonPath('discarded.data.0.id', $next->id);

        $this->travel(1)->seconds();
        $this->deleteJson('/panel/questions/'.$last->id)->assertNoContent();

        $this->assertDatabaseMissing('questions', ['id' => $questions->get(98)->id]);
        $this->assertSoftDeleted($last);
        $this->assertNotSoftDeleted($active);
        $this->getJson('/panel/data')->assertOk()->assertJsonPath('discarded_count', 100);
        $this->assertDatabaseCount('questions', 101);
    }

    public function test_discards_in_the_same_second_follow_discard_order_instead_of_question_id(): void
    {
        $this->freezeTime();
        $older = $this->createQuestion();
        $newer = $this->createQuestion();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->deleteJson('/panel/questions/'.$newer->id)->assertNoContent();
        $this->travel(1)->milliseconds();
        $this->deleteJson('/panel/questions/'.$older->id)->assertNoContent();

        $this->getJson('/panel/data?discarded=1')->assertOk()
            ->assertJsonPath('discarded.data.0.id', $older->id)
            ->assertJsonPath('discarded.data.1.id', $newer->id);
    }

    public function test_discarded_questions_cannot_be_selected_answered_or_discarded_again(): void
    {
        $question = $this->createQuestion();
        $question->delete();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->patchJson('/panel/questions/'.$question->id, ['selected' => true, 'answered' => true])->assertNotFound();
        $this->deleteJson('/panel/questions/'.$question->id)->assertNotFound();
        $this->deleteJson('/panel/questions/999999')->assertNotFound();

        $this->assertSoftDeleted($question);
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'selected' => false, 'answered' => false]);
    }

    public function test_concurrent_discard_returns_429_without_changing_the_question(): void
    {
        $question = $this->createQuestion();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));
        $lock = Cache::lock('questions:discard', 15);
        $lock->get();

        try {
            $this->deleteJson('/panel/questions/'.$question->id)
                ->assertTooManyRequests()->assertHeader('Retry-After', '1')
                ->assertJsonPath('message', 'Hay un descarte en curso. Intentá nuevamente.');
            $this->assertNotSoftDeleted($question);
        } finally {
            $lock->release();
        }

        $this->deleteJson('/panel/questions/'.$question->id)->assertNoContent();
        $this->assertSoftDeleted($question);
    }

    #[TestWith([false, false])]
    #[TestWith([true, false])]
    #[TestWith([false, true])]
    #[TestWith([true, true])]
    public function test_restoring_a_discarded_question_returns_it_to_received(bool $selected, bool $answered): void
    {
        $question = $this->createQuestion();
        $question->update(['selected' => $selected, 'answered' => $answered]);
        $question->delete();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->patchJson('/panel/questions/'.$question->id.'/restore', ['name' => 'Replaced'])->assertNoContent()
            ->assertHeader('Cache-Control', 'no-store, private');

        $this->assertNotSoftDeleted($question);
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'name' => $question->name, 'email' => $question->email, 'question' => $question->question, 'selected' => false, 'answered' => false]);
        $this->getJson('/panel/data')->assertOk()
            ->assertJsonPath('received.data.0.id', $question->id)
            ->assertJsonCount(0, 'selected')->assertJsonPath('discarded_count', 0);
        $this->getJson('/panel/data?discarded=1')->assertOk()->assertJsonCount(0, 'discarded.data');
        $this->getJson('/panel/data?answered=1')->assertOk()->assertJsonCount(0, 'answered.data');
    }

    public function test_restore_rejects_active_and_permanently_removed_questions(): void
    {
        $active = $this->createQuestion();
        $removed = $this->createQuestion();
        $removed->forceDelete();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));

        $this->patchJson('/panel/questions/'.$active->id.'/restore')->assertNotFound();
        $this->patchJson('/panel/questions/'.$removed->id.'/restore')->assertNotFound();

        $this->assertNotSoftDeleted($active);
        $this->assertDatabaseMissing('questions', ['id' => $removed->id]);
    }

    public function test_restore_waits_for_an_in_progress_discard_and_can_be_retried(): void
    {
        $question = $this->createQuestion();
        $question->delete();
        $this->actingAs(User::factory()->create(['email' => User::PANEL_EMAIL]));
        $lock = Cache::lock('questions:discard', 15);
        $lock->get();

        try {
            $this->patchJson('/panel/questions/'.$question->id.'/restore')
                ->assertTooManyRequests()->assertHeader('Retry-After', '1');
            $this->assertSoftDeleted($question);
        } finally {
            $lock->release();
        }

        $this->patchJson('/panel/questions/'.$question->id.'/restore')->assertNoContent();
        $this->assertNotSoftDeleted($question);
        $this->patchJson('/panel/questions/'.$question->id.'/restore')->assertNotFound();
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
